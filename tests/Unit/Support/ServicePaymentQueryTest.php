<?php

namespace Tests\Unit\Support;

use App\Support\ServicePaymentQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class ServicePaymentQueryTest extends TestCase
{
    public function test_where_paid_matches_legacy_and_modern_status_values(): void
    {
        $model = new class extends Model
        {
            protected $table = 'membership_transactions';
        };

        /** @var Builder $query */
        $query = ServicePaymentQuery::wherePaid($model->newQuery());
        $sql = $query->toSql();

        $this->assertStringContainsString('LOWER(status) IN', $sql);
        $this->assertSame(['completed', 'payment_received', 'paid', 'success'], $query->getBindings());
    }
}
