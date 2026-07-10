<?php

use App\Support\ServicePaymentQuery;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

test('where paid matches legacy and modern status values', function () {
    $model = new class extends Model
    {
    };
    /** @var Builder $query */
    $query = ServicePaymentQuery::wherePaid($model->newQuery());
    $sql = $query->toSql();

    $this->assertStringContainsString('LOWER(status) IN', $sql);
    expect($query->getBindings())->toBe(['completed', 'payment_received', 'paid', 'success']);
});
