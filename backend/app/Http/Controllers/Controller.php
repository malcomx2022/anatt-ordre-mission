<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Http\Traits\HasPagination;

abstract class Controller
{
    use ApiResponse, HasPagination;
}
