<?php

declare(strict_types=1);

namespace Yurun\TDEngine\Orm\ClientHandler\Extension;

use Yurun\TDEngine\Orm\ClientHandler\IClientHandler;
use Yurun\TDEngine\Orm\Contract\IQueryResult;

class Handler implements IClientHandler
{
    /**
     * 查询.
     */
    public function query(string $sql, ?string $clientName = null): IQueryResult
    {
        return new QueryResult(ConnectionManager::query($sql, $clientName));
    }
}
