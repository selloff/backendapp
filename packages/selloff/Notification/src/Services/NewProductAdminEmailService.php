<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class NewProductAdminEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    public function queue(Product $product): ?EmailJob
    {
        $to = $this->viewData->adminRecipient();

        if ($to === null) {
            return null;
        }

        $data = $this->viewData->forProduct($product);
        $subject = 'New product added';

        return $this->email->queue(
            TransactionalEmailType::NEW_PRODUCT,
            $to,
            [
                'title' => 'New product added',
                'content' => 'A vendor has added a new product. Review it on Selloff.',
                'url' => $data['productUrl'],
                'buttonText' => 'View product',
                'productTitle' => $data['productTitle'],
            ],
            subject: $subject,
            template: 'main',
        );
    }
}
