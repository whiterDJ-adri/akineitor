<?php

namespace Modules\Health;

use Core\Request;
use Core\Response;

class HealthController
{
    public function status(Request $req, Response $res): void
    {
        $res::json([
            'status' => 'ok',
        ]);
    }
}
