<?php

namespace App\Models;

use App\Util\Badge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $table = 'customers';
    protected $fillable = [
        'account_no',
        'name',
        'id_no',
        'phone_no',
        'email',
        'email_verified_at',
        'status',
    ];

    protected static function boot(){
        parent::boot();

        // static::created(function($customer){
            // Generate account number: yearmonth + 4-digit customer ID
            //$yearMonth = now()->format('Ym'); // Current year and month (e.g., 202501)
            //$customerId = str_pad($customer->id, 4, '0', STR_PAD_LEFT); // Pad customer ID to 4 digits
            //$customer->account_no = $yearMonth . $customerId;
            //$customer->save(); // Save the updated account number

            // Get phone number in the format 2547XXXXXXXX
            // Remove the first 3 characters to get 7XXXXXXXX
            /*if (isset($customer->phone_no) && strlen($customer->phone_no) >= 10) {
                $customer->account_no = substr($customer->phone_no, 3);
                $customer->save(); // Save the updated account number
            }*/
        // });
    }

    public function wallet(){
        return $this->hasOne(Wallet::class);
    }

    public function coin(){
        return $this->hasOne(Coin::class);
    }

    public function gameWalletTransactions(){
        return $this->hasMany(GameTransaction::class);
    }

    public function walletTransactions(){
        return $this->hasMany(WalletTransaction::class);
    }

    public function competitionWalletTransactions(){
        return $this->hasMany(CompetitionTransaction::class);
    }

    public function competitionWallet(){
        return $this->hasMany(CompetitionWallet::class);
    }
    
    public function allCompetitionTransactions(): HasManyThrough{
        return $this->hasManyThrough(
            CompetitionTransaction::class,
            CompetitionWallet::class,
            'customer_id', // Foreign key on competition_wallets table
            'competition_wallet_id', // Foreign key on competition_transactions table
            'id', // Local key on customers table
            'id' // Local key on competition_wallets table
        );
    }

    public function purchases(): HasMany {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Get the status badge HTML for this role.
     *
     * @return string
     */
    public function getStatusBadge(): string {
        return match ($this->status) {
            1 => Badge::set('primary', 'Active'),
            2 => Badge::set('danger', 'Blocked'),
            3 => Badge::set('secondary', 'Confirmed'),
            4 => Badge::set('default', 'Deleted'),
            5 => Badge::set('warning', 'Fraud'),
            6 => Badge::set('info', 'Lead'),
            default => Badge::set('secondary', 'NONE'),
        };
    }
}
