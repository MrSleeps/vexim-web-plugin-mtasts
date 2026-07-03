<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use VEximweb\Plugin\MTASTS\Http\Controllers\MTASTSController;

Route::get('.well-known/mta-sts.txt', [MTASTSController::class, 'showTxtFile']);