<?php

declare(strict_types=1);

namespace Yurun\TDEngine;

class TDEngineManager
{
    /**
     * 默认客户端名.
     *
     * @var string|null
     */
    private static $defaultClientName;

    /**
     * 客户端配置.
     *
     * @var ClientConfig[]
     */
    private static $clientConfigs = [];

    /**
     * 客户端集合.
     *
     * @var Client[]
     */
    private static $clients = [];

    private function __construct()
    {
    }

    /**
     * 设置客户端配置.
     */
    public static function setClientConfig(string $clientName, ClientConfig $config): void
    {
        static::$clientConfigs[$clientName] = $config;
    }

    /**
     * 获取客户端配置.
     */
    public static function getClientConfig(?string $clientName = null): ?ClientConfig
    {
        $clientName = static::getClientName($clientName);

        return static::$clientConfigs[$clientName] ?? null;
    }

    /**
     * 移除客户端配置.
     */
    public static function removeClientConfig(?string $clientName = null): void
    {
        $clientName = static::getClientName($clientName);
        if (isset(static::$clientConfigs[$clientName]))
        {
            unset(static::$clientConfigs[$clientName]);
        }
    }

    /**
     * 移除已缓存的客户端实例（配置保留）.
     *
     * 与 {@see getClient()} 使用相同的 {@see getClientName()} 解析规则。
     * 下次 {@see getClient()} 会重新构造 {@see Client} 与 HTTP 会话，可用于网络异常后换连接。
     */
    public static function removeClient(?string $clientName = null): void
    {
        $clientName = static::getClientName($clientName);
        if (isset(static::$clients[$clientName]))
        {
            unset(static::$clients[$clientName]);
        }
    }

    /**
     * 设置默认客户端名.
     */
    public static function setDefaultClientName(string $clientName): void
    {
        static::$defaultClientName = $clientName;
    }

    /**
     * 获取默认客户端名.
     */
    public static function getDefaultClientName(): ?string
    {
        return static::$defaultClientName;
    }

    /**
     * 获取 TDengine 客户端.
     */
    public static function getClient(?string $clientName = null): Client
    {
        $clientName = static::getClientName($clientName);
        if (isset(static::$clients[$clientName]))
        {
            return static::$clients[$clientName];
        }
        if (!isset(static::$clientConfigs[$clientName]))
        {
            throw new \RuntimeException(sprintf('Client %s config does not found', $clientName));
        }
        $client = new Client(static::$clientConfigs[$clientName]);

        return static::$clients[$clientName] = $client;
    }

    /**
     * 获取客户端名称.
     *
     * @return string
     */
    public static function getClientName(?string $clientName = null): ?string
    {
        return $clientName ?: static::$defaultClientName;
    }
}
