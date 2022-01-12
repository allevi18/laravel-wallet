<?php

namespace Bavix\Wallet\Models;

use Kyslik\ColumnSortable\Sortable;
use Nicolaslopezj\Searchable\SearchableTrait;
use function array_merge;
use Bavix\Wallet\Interfaces\Mathable;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Wallet as WalletModel;
use Bavix\Wallet\Services\WalletService;
use function config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Transaction.
 *
 * @property string $payable_type
 * @property int $payable_id
 * @property int $wallet_id
 * @property string $uuid
 * @property string $type
 * @property int|string $amount
 * @property float $amountFloat
 * @property bool $confirmed
 * @property array $meta
 * @property Wallet $payable
 * @property WalletModel $wallet
 */
class Transaction extends Model
{
    use Sortable;
    use SearchableTrait;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAW = 'withdraw';

    /**
     * @var array
     */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'wallet_id',
        'uuid',
        'type',
        'amount',
        'confirmed',
        'meta',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'wallet_id' => 'int',
        'confirmed' => 'bool',
        'meta' => 'json',
    ];

    public $sortable = [
        'id',
        'type',
        'amount',
        'created_at'
    ];

    protected $searchable = [
        'columns' => [
            'transactions.uuid' => 10,
            'users.email' => 10,
            'users.username' => 10,
        ],
        'joins' => [
            'users' => ['transactions.payable_id','users.id'],
            //'sellers' => ['listings.user_id','listings.id'],
        ],
    ];



    /**
     * {@inheritdoc}
     */
    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            config('wallet.transaction.casts', [])
        );
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        if (! $this->table) {
            $this->table = config('wallet.transaction.table', 'transactions');
        }

        return parent::getTable();
    }

    /**
     * @return MorphTo
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(config('wallet.wallet.model', WalletModel::class));
    }

    /**
     * @return int|float
     */
    public function getAmountFloatAttribute()
    {

        $decimalPlacesValue = app(WalletService::class)->decimalPlacesValue($this->wallet);
        $decimalPlaces = app(WalletService::class)
            ->decimalPlaces($this->wallet);

        return app(Mathable::class)
            ->div($this->amount, $decimalPlaces, $decimalPlacesValue);
    }

    /**
     * @param int|float $amount
     *
     * @return void
     */
    public function setAmountFloatAttribute($amount): void
    {
        $math = app(Mathable::class);
        $decimalPlaces = app(WalletService::class)
            ->decimalPlaces($this->wallet);

        $this->amount = $math->round($math->mul($amount, $decimalPlaces));
    }

    public function sourceSortable($query, $direction)
    {
//        return $query->orderBy('meta->source', $direction);
        return $query
            ->orderByRaw("FIELD(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) ,'purchase', 'cancel', 'granted', 'order', 'penalty', 'transfer', 'payout') $direction")
            ->orderBy('id', $direction);
    }

    public function orderidSortable($query, $direction)
    {
        return $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.order_id')) AS DECIMAL) $direction");
    }

    public function payoutidSortable($query, $direction)
    {
        return $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.payout_id')) AS DECIMAL) $direction");
    }
}
