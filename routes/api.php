<?php

use App\Http\Controllers\api\v1\B2CBalanceController;
use App\Http\Controllers\api\v1\B2Controller;
use App\Http\Controllers\api\v1\B2CResultController;
use App\Http\Controllers\api\v1\B2CTimeOutController;
use App\Http\Controllers\api\v1\CoinBuyController;
use App\Http\Controllers\api\v1\CoinController;
use App\Http\Controllers\api\v1\CoinExchangeController;
use App\Http\Controllers\api\v1\CoinTransferController;
use App\Http\Controllers\api\v1\CompetitionTransactionController;
use App\Http\Controllers\api\v1\CompetitionWalletController;
use App\Http\Controllers\api\v1\CompetitionWalletTransferPayoutController;
use App\Http\Controllers\api\v1\CompetitionWalletWithdrawController;
use App\Http\Controllers\api\v1\ConfirmationController;
use App\Http\Controllers\api\v1\CustomerController;
use App\Http\Controllers\api\v1\DecryptIdentifierController;
use App\Http\Controllers\api\v1\DepositController;
use App\Http\Controllers\api\v1\DropConnectionController;
use App\Http\Controllers\api\v1\EmailVerifiedController;
use App\Http\Controllers\api\v1\EncryptIdentifierController;
use App\Http\Controllers\api\v1\GameCreditController;
use App\Http\Controllers\api\v1\GameTransactionController;
use App\Http\Controllers\api\v1\GameWalletController;
use App\Http\Controllers\api\v1\GameWalletWithdrawController;
use App\Http\Controllers\api\v1\PlaygroundController;
use App\Http\Controllers\api\v1\PurchaseController;
use App\Http\Controllers\api\v1\RegisterC2BUrlsController;
use App\Http\Controllers\api\v1\StatsController;
use App\Http\Controllers\api\v1\StkCallbackController;
use App\Http\Controllers\api\v1\StkDepositController;
use App\Http\Controllers\api\v1\StkLoadController;
use App\Http\Controllers\api\v1\TestController;
use App\Http\Controllers\api\v1\TransactionController;
use App\Http\Controllers\api\v1\ValidationController;
use App\Http\Controllers\api\v1\WalletController;
use App\Http\Controllers\api\v1\WalletTransactionController;
use App\Http\Controllers\api\v1\WalletTransferController;
use App\Http\Controllers\api\v1\WithdrawalController;
use App\Http\Controllers\api\v1\WithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function () {
    Route::middleware(['apikey.checker', 'log.requests'])->group(function () {
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store'])->middleware(['idempotency', 'throttle:write']);
        Route::get('/customers/search', [CustomerController::class, 'search']);
        Route::post('/customers/referrals', [CustomerController::class, 'customersReferrals']);
        // Customer Leaderboard Route
        Route::post('/customers/leaderboard', [CustomerController::class, 'customer_leaderboard']);
        Route::post('/customers/combined-leaderboard', [CustomerController::class, 'combined_leaderboard']);

        Route::get('/wallets', [WalletController::class, 'index']);

        Route::get('/wallets/transactions', [WalletTransactionController::class, 'index']);
        Route::get('/wallets/today', [WalletTransactionController::class, 'showDailyAmount']);

        Route::get('/coins', [CoinController::class, 'index']);

        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::post('/purchases/referrals', [PurchaseController::class, 'referralPurchases']);

        Route::get('/deposits', [DepositController::class, 'index']);
        Route::post('/deposits', [DepositController::class, 'store'])->middleware(['idempotency', 'throttle:write']);

        Route::get('/withdraws', [WithdrawController::class, 'index']);

        Route::get('/game/wallets', [GameWalletController::class, 'index']);
        Route::post('/game/wallets', [GameWalletController::class, 'store'])->middleware(['idempotency', 'throttle:write']);

        Route::get('/game/results', [GameWalletController::class, 'game_results']);

        Route::get('/game/bets', [GameTransactionController::class, 'index']);
        Route::post('/game/bets', [GameTransactionController::class, 'store'])->middleware(['idempotency', 'throttle:write']);

        Route::post('/game/credit', GameCreditController::class)->middleware(['idempotency', 'throttle:write']);

        Route::get('/competition/wallets', [CompetitionWalletController::class, 'index']);
        Route::post('/competition/wallets', [CompetitionWalletController::class, 'store'])->middleware(['idempotency', 'throttle:write']);

        Route::get('/competition/transactions', [CompetitionTransactionController::class, 'index']);
        Route::post('/competition/transactions', [CompetitionTransactionController::class, 'store'])->middleware(['idempotency', 'throttle:write']);

        // Competition Wallet Payout Transaction Route
        Route::post('/competition/payout', CompetitionWalletTransferPayoutController::class)->middleware(['idempotency', 'throttle:write']);

        // Get B2C Balance
        Route::get('/b2c/balance', [B2Controller::class, 'index']);

        // Stats routes
        Route::middleware('throttle:stats')->group(function () {
            Route::get('/stats/customers', [StatsController::class, 'customerStats']);
            Route::get('/stats/income', [StatsController::class, 'incomeStats']);
            Route::get('/stats/income/daily-30-days', [StatsController::class, 'dailyIncomeStats30Days']);
            Route::get('/stats/purchases', [StatsController::class, 'purchaseStats']);
            Route::get('/stats/played', [StatsController::class, 'playedStats']);
            Route::get('/stats/retention', [StatsController::class, 'retentionRates']);
            Route::post('/stats/purchases/referrals', [StatsController::class, 'purchaseReferralsStats']);
            Route::post('/stats/customers/referrals', [StatsController::class, 'customerReferralStats']);
            Route::post('/stats/customers/played', [StatsController::class, 'playedByPlayerStats']);
        });

        Route::get('/playground', [PlaygroundController::class, 'index']);
        Route::post('/playground', [PlaygroundController::class, 'store']);

        Route::middleware('decrypt.identifier')->group(function () {
            // Customer Routes
            Route::get('/customers/{encryptedIdentifier}', [CustomerController::class, 'show']);
            Route::put('/customers/{encryptedIdentifier}', [CustomerController::class, 'update']);
            Route::put('/customers/{encryptedIdentifier}/wallet', [CustomerController::class, 'update_wallet']);
            Route::delete('/customers/{encryptedIdentifier}', [CustomerController::class, 'destroy']);
            // Customer Email Verification Routes
            Route::get('/customers/{encryptedIdentifier}/verify-email', EmailVerifiedController::class);
            // Customer Transactions Routes
            Route::post('/customers/transactions/{encryptedIdentifier}', [CustomerController::class, 'customer_transactions']);
            // Customer Games Played Route
            Route::get('/customers/played/{encryptedIdentifier}', [CustomerController::class, 'customer_played']);
            // Customer Purchases Routes
            Route::get('/customers/purchases/{encryptedIdentifier}', [CustomerController::class, 'customer_purchases']);

            // Wallet Routes
            Route::get('/wallets/{encryptedIdentifier}', [WalletController::class, 'show']);
            Route::put('/wallets/{encryptedIdentifier}', [WalletController::class, 'update']);
            Route::put('/wallets/{encryptedIdentifier}/balance', [WalletController::class, 'update_balance']);
            Route::put('/wallets/{encryptedIdentifier}/withdraw', [WalletController::class, 'reduce_balance']);
            Route::put('/wallets/{encryptedIdentifier}/deposit', [WalletController::class, 'add_balance']);

            // Wallet Transactions Routes
            Route::get('/wallets/transactions/{encryptedIdentifier}', [WalletTransactionController::class, 'show']);

            // Purchases Routes
            Route::get('/purchases/{encryptedIdentifier}', [PurchaseController::class, 'show']);
            Route::delete('/purchases/{encryptedIdentifier}', [PurchaseController::class, 'destroy']);

            // Coins Wallets Routes
            Route::get('/coins/{encryptedIdentifier}', [CoinController::class, 'show']);
            Route::put('/coins/{encryptedIdentifier}', [CoinController::class, 'update']);

            // Transactions Routes
            Route::get('/transactions', [TransactionController::class, 'index']);
            Route::get('/transactions/{encryptedIdentifier}', [TransactionController::class, 'show']);

            // Deposit/Payment Routes
            Route::get('/deposits/{encryptedIdentifier}', [DepositController::class, 'show']);
            Route::put('/deposits/{encryptedIdentifier}', [DepositController::class, 'update']);

            // Wallet Transfer Routes
            Route::post('/wallets/transfer/{encryptedIdentifier}', WalletTransferController::class)->middleware(['idempotency', 'throttle:write']);

            // Coins Action Routes
            Route::post('/coins/buy/{encryptedIdentifier}', CoinBuyController::class)->middleware(['idempotency', 'throttle:write']);
            Route::post('/coins/transfer/{encryptedIdentifier}', CoinTransferController::class)->middleware(['idempotency', 'throttle:write']);
            Route::put('/coins/exchange/{encryptedIdentifier}', CoinExchangeController::class)->middleware(['idempotency', 'throttle:write']);

            // Withdraw Transaction Routes
            Route::get('/withdraws/{encryptedIdentifier}', [WithdrawController::class, 'show']);

            // Sensitive operation routes here
            Route::middleware(['throttle:financial', 'idempotency'])->group(function () {
                // StkPush / M-PESA Express Deposit
                Route::post('/deposits/{encryptedIdentifier}', StkDepositController::class);
                Route::post('/load/{encryptedIdentifier}', StkLoadController::class);
                // Withdrawal route
                // Route::post('/withdraw/{encryptedIdentifier}', WithdrawalController::class);
            });

            // Game Wallets Routes
            Route::get('/game/wallets/{encryptedIdentifier}', [GameWalletController::class, 'show']);
            Route::put('/game/wallets/{encryptedIdentifier}', [GameWalletController::class, 'update']);
            Route::delete('/game/wallets/{encryptedIdentifier}', [GameWalletController::class, 'destroy']);

            // Game Incomes Routes
            Route::post('/game/income', [GameWalletController::class, 'game_income']);

            // Game Wallets Drop Connection
            Route::post('/game/drop/{encryptedIdentifier}', DropConnectionController::class)->middleware(['idempotency', 'throttle:write']);

            // Game Wallets Deposit Transactions Routes
            Route::get('/game/bets/{encryptedIdentifier}', [GameTransactionController::class, 'show']);
            Route::put('/game/bets/{encryptedIdentifier}', [GameTransactionController::class, 'update']);
            Route::delete('/game/bets/{encryptedIdentifier}', [GameTransactionController::class, 'destroy']);

            // Game Wallet Payout Transaction Route
            Route::post('/game/withdraw/{encryptedIdentifier}', GameWalletWithdrawController::class)->middleware(['idempotency', 'throttle:write']);

            // Competition Wallets Routes
            Route::get('/competitions/{encryptedIdentifier}', [CompetitionWalletController::class, 'show_competitions']);
            Route::get('/competition/wallets/{encryptedIdentifier}', [CompetitionWalletController::class, 'show']);
            Route::put('/competition/wallets/{encryptedIdentifier}', [CompetitionWalletController::class, 'update']);
            Route::delete('/competition/wallets/{encryptedIdentifier}', [CompetitionWalletController::class, 'destroy']);

            // Income By Competition Type & Rounds
            Route::post('/competition/income/{encryptedIdentifier}', [CompetitionWalletController::class, 'competition_income']);
            // Competition Results & Income Breakdown
            Route::get('competition/results/{encryptedIdentifier}', [CompetitionWalletController::class, 'competition_results']);
            // Competition Awards
            Route::get('/competition/awards/{encryptedIdentifier}', [CompetitionWalletController::class, 'competition_awards']);

            // Competition Wallets Deposit Transactions Routes
            Route::get('/competition/transactions/{encryptedIdentifier}', [CompetitionTransactionController::class, 'show']);
            Route::put('/competition/transactions/{encryptedIdentifier}', [CompetitionTransactionController::class, 'update']);
            Route::delete('/competition/transactions/{encryptedIdentifier}', [CompetitionTransactionController::class, 'destroy']);

            // Competition Wallet Payout Transaction Route
            Route::post('/competition/withdraw/{encryptedIdentifier}', CompetitionWalletWithdrawController::class)->middleware(['idempotency', 'throttle:write']);

            // Playground Routes
            Route::get('/playground/{encryptedIdentifier}', [PlaygroundController::class, 'show'])->withoutMiddleware('decrypt.identifier');
            Route::put('/playground/{encryptedIdentifier}', [PlaygroundController::class, 'update']);
            Route::delete('/playground/{encryptedIdentifier}', [PlaygroundController::class, 'destroy']);
        });

        // Encrypt & Decrypt Identifier
        Route::post('/encrypt', EncryptIdentifierController::class);
        Route::post('/decrypt', DecryptIdentifierController::class);

        // Register C2B Callback URLs
        Route::get('/c2b/register', RegisterC2BUrlsController::class);

        // Testing MPESA API
        // Route::get('/test', [TestController::class, 'index']);
        /*Route::middleware('decryptIdentifier')->get('/test/{encryptedIdentifier}', function ($decryptedIdentifier) {
            return response()->json(['decrypted' => $decryptedIdentifier]);
        });*/

        // Users
        Route::get('/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');
    });

    // Route::get('/test', [TestController::class, 'index']);

    // Callback URLS
    Route::middleware('throttle:callback')->group(function () {
        Route::post('/stk/callback', StkCallbackController::class);
        Route::post('/c2b/confirm', ConfirmationController::class);
        Route::post('/c2b/validate', ValidationController::class);
        Route::post('/b2c/result', B2CResultController::class);
        Route::post('/b2c/timeout', B2CTimeOutController::class);
        Route::post('/b2c/balance/result', B2CBalanceController::class);
    });
});
