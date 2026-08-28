<?php

use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PromotionStatusController;
use App\Http\Controllers\PromotionSummaryController;
use Illuminate\Support\Facades\Route;

// Must stay above every route carrying {promotion}. The router walks the
// routes of the incoming verb in declaration order and takes the first match,
// so a parameterised route declared first would swallow this URI: the binder
// would look for a promotion keyed by the literal "summary" and the endpoint
// would answer 404, with nothing but the status to say why.
//
// Today no GET route carries {promotion}, so only routes of another verb
// could compete and the hazard is dormant. That is precisely why the order is
// worth stating: the day a `GET promotions/{promotion}` is added, this line
// being first is the whole difference, and nothing would fail loudly to point
// at it. PromotionRoutingTest asserts the order for that reason.
Route::get('promotions/summary', PromotionSummaryController::class);

Route::get('promotions', [PromotionController::class, 'index']);
Route::post('promotions', [PromotionController::class, 'store']);
Route::patch('promotions/{promotion}', [PromotionController::class, 'update']);
Route::patch('promotions/{promotion}/status', PromotionStatusController::class);
Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy']);
