<?php

declare(strict_types=1);

namespace Yurun\TDEngine\Orm\ClientHandler\Extension;

use TDengine\Connection;
use TDengine\Resource;
use Yurun\TDEngine\TDEngineManager;

/**
 * TDengine 扩展原生连接：PHP-FPM Worker 进程内按客户端逻辑名缓存复用；
 * 取出时若有 {@see Connection::isConnected()} 则校验；查询遇连接类错误则丢弃缓存并重试一次。
 */
final class ConnectionManager
{
    /**
     * TAOS_DEF_ERROR_CODE(0, suffix) 形式下与链路/超时/无效连接等相关后缀（模块字非 0 的不在此列）.
     *
     * @var int[]
     */
    private const RETRYABLE_RPC0_LOW16 = [
        0x0004, 0x0005, 0x000B, 0x0014, 0x0015, 0x0018, 0x0019, 0x0020, 0x020B,
    ];

    /**
     * @var array<string, Connection>
     */
    private static $connections = [];

    private function __construct()
    {
    }

    /**
     * 执行 SQL；若抛出连接类 TDengineException，会使缓存失效并重建连接后再执行至多一次。
     */
    public static function query(string $sql, ?string $clientName = null): Resource
    {
        $key = self::resolveCacheKey($clientName);
        $attempt = 0;
        while (true)
        {
            try
            {
                return self::getConnection($clientName)->query($sql);
            }
            catch (\Throwable $e)
            {
                if ($attempt++ > 0 || !self::shouldRetryAfterTdengineFailure($e))
                {
                    throw $e;
                }
                self::invalidate($key);
            }
        }
    }

    public static function getConnection(?string $clientName = null): Connection
    {
        $key = self::resolveCacheKey($clientName);
        if (isset(self::$connections[$key]))
        {
            $existing = self::$connections[$key];
            if (!\method_exists($existing, 'isConnected') || $existing->isConnected())
            {
                return $existing;
            }
            self::invalidate($key);
        }
        $config = TDEngineManager::getClientConfig($clientName);
        if (!$config)
        {
            throw new \RuntimeException(sprintf('Client %s config does not found', $key));
        }
        $db = $config->getDb();
        $connection = new Connection($config->getHost(), $config->getPort(), $config->getUser(), $config->getPassword(), '' === $db ? null : $db);
        $connection->connect();

        return self::$connections[$key] = $connection;
    }

    /** @param string|null $clientName `null` 表示关闭全部缓存连接 */
    public static function disconnect(?string $clientName = null): void
    {
        if (null === $clientName)
        {
            foreach (self::$connections as $conn) {
                self::closeConnection($conn);
            }
            self::$connections = [];

            return;
        }
        self::invalidate(self::resolveCacheKey($clientName));
    }

    private static function invalidate(string $key): void
    {
        if (!isset(self::$connections[$key]))
        {
            return;
        }
        self::closeConnection(self::$connections[$key]);
        unset(self::$connections[$key]);
    }

    private static function resolveCacheKey(?string $clientName): string
    {
        $resolved = TDEngineManager::getClientName($clientName);

        return null === $resolved ? '' : $resolved;
    }

    private static function closeConnection(Connection $connection): void
    {
        if (\method_exists($connection, 'close'))
        {
            $connection->close();
        }
    }

    private static function shouldRetryAfterTdengineFailure(\Throwable $e): bool
    {
        if (!\class_exists(\TDengine\Exception\TDengineException::class))
        {
            return false;
        }
        if (!$e instanceof \TDengine\Exception\TDengineException)
        {
            return false;
        }
        $code = $e->getCode();
        $u = $code < 0 ? $code + 4294967296 : $code;
        if ((($u >> 16) & 0xFFFF) === 0x80FF)
        {
            return true;
        }
        if (($u & 0xFFFF0000) !== 0x80000000)
        {
            return false;
        }

        return \in_array($u & 0xFFFF, self::RETRYABLE_RPC0_LOW16, true);
    }
}
