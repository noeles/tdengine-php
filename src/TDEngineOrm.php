<?php

declare(strict_types=1);

namespace Yurun\TDEngine\Orm;

use Yurun\TDEngine\Orm\ClientHandler\Extension\Handler as ExtensionHandler;
use Yurun\TDEngine\Orm\ClientHandler\IClientHandler;
use Yurun\TDEngine\Orm\ClientHandler\Restful\Handler as RestfulHandler;

class TDEngineOrm
{
    /**
     * @var IClientHandler|null
     */
    private static $clientHandler;

    private function __construct()
    {
    }

    /**
     * 设置客户端（自定义 {@see IClientHandler}，或使用 {@see useNativeExtension()} / {@see useRestHandler()}）。
     */
    public static function setClientHandler(IClientHandler $clientHandler): void
    {
        self::$clientHandler = $clientHandler;
    }

    /**
     * 使用 TDengine PHP 扩展（原生客户端，进程内 {@see ClientHandler\Extension\ConnectionManager} 复用连接）。
     *
     * @throws \RuntimeException 未加载 ext-tdengine（不存在 {@see \TDengine\Connection}）时
     */
    public static function useNativeExtension(): void
    {
        if (!\class_exists(\TDengine\Connection::class))
        {
            throw new \RuntimeException(
                'TDengine PHP extension is required for native client (class TDengine\\Connection). Install ext-tdengine or keep the default REST handler.'
            );
        }

        self::setClientHandler(new ExtensionHandler());
    }

    /**
     * 使用 HTTP REST（taosAdapter），无需 PHP 扩展（与首次 {@see getClientHandler()} 的默认一致）。
     */
    public static function useRestHandler(): void
    {
        self::setClientHandler(new RestfulHandler());
    }

    /**
     * 获取客户端处理实现。
     *
     * 若未调用 {@see setClientHandler()} / {@see useNativeExtension()} / {@see useRestHandler()}，则默认懒加载 {@see RestfulHandler}。
     */
    public static function getClientHandler(): IClientHandler
    {
        if (self::$clientHandler)
        {
            return self::$clientHandler;
        }

        return self::$clientHandler = new RestfulHandler();
    }
}
