<?php

use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PromotionStatusController;
use App\Http\Controllers\PromotionSummaryController;
use Illuminate\Support\Facades\Route;

// Must stay above every route carrying {promotion}. The router walks the
// routes of the incoming verb in declaration order and takes the first match,
// so a parameterised route declared first would swallow this URI and the
// summary would become unreachable, with nothing but the status to say why.
//
// Today no GET route carries {promotion}, so only routes of another verb
// could compete and the hazard is dormant. Those other verbs no longer reach
// the database either, thanks to the numeric constraint below: DELETE on this
// URI now answers 405, because the path exists and only the method does not. That is precisely why the order is
// worth stating: the day a `GET promotions/{promotion}` is added, this line
// being first is the whole difference, and nothing would fail loudly to point
// at it. PromotionRoutingTest asserts the order for that reason.
Route::get('promotions/summary', PromotionSummaryController::class);

Route::get('promotions', [PromotionController::class, 'index']);
Route::post('promotions', [PromotionController::class, 'store']);

// The numeric constraint is not decoration. Without it any literal segment
// reaches the model binder, which hands the string to a bigint column and
// PostgreSQL answers "invalid input syntax for type bigint", a 500 that
// blames the database for a URI that simply names no promotion. Constraining
// the parameter keeps such a request from ever reaching SQL: it stops being a
// column type error and becomes a route that does not match. The alternative,
// catching QueryException, would have turned every real SQL failure into a
// misleading 404. ApiErrorMappingTest covers both DELETE promotions/summary
// and a non numeric identifier.
Route::patch('promotions/{promotion}', [PromotionController::class, 'update'])
    ->whereNumber('promotion');
Route::patch('promotions/{promotion}/status', PromotionStatusController::class)
    ->whereNumber('promotion');
Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])
    ->whereNumber('promotion');
