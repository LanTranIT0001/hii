<?php

declare(strict_types=1);

namespace Core;

use PDO;

abstract class Model
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
}
