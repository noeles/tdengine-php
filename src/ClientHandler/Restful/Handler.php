<?php

declare(strict_types=1);

namespace Yurun\TDEngine\Orm\ClientHandler\Restful;

use Yurun\TDEngine\Action\Sql\SqlResult;
use Yurun\TDEngine\Orm\ClientHandler\IClientHandler;
use Yurun\TDEngine\Orm\Contract\IQueryResult;
use Yurun\TDEngine\TDEngineManager;

class Handler implements IClientHandler
{
    /**
     * 进程内连接池，须实现 `run(?string $ormClientName, callable $fn): mixed`，且 callable 收到 Yurun `Client`。
     *
     * 未设置时沿用 {@see TDEngineManager::getClient()} 短路径。
     *
     * @var object|null
     */
    private static $connectionPool;

    /**
     * 注册连接池（通常仅 MQ 常驻进程在引导时调用一次；非 MQ 不要设置）。
     *
     * @param object|null $pool 需含 `run(?string, callable): SqlResult`，如 TdengineRestConnectionPool
     */
    public static function setConnectionPool(?object $pool): void
    {
        self::$connectionPool = $pool;
    }

    /**
     * 查询
     * @param string $sql
     * @param string|null $name
     * @return IQueryResult
     */
    public function query(string $sql, ?string $name = null): IQueryResult
    {
        $pool = self::$connectionPool;
        if ($pool !== null) {
            /** @var SqlResult $sqlResult */
            $sqlResult = $pool->run(
                $name,
                static function ($client) use ($sql): SqlResult {
                    return $client->sql($sql);
                }
            );

            return new QueryResult($sqlResult);
        }

        return new QueryResult(TDEngineManager::getClient($name)->sql($sql));
    }
}