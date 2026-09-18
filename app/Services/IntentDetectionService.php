<?php

namespace App\Services;

class IntentDetectionService
{
    public function detect(string $message): string
    {
        $text = strtolower(trim($message));

        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        $intents = [

            'product' => [
                'product',
                'products',
                'scooter',
                'bike',
                'battery',
                'batteries',
                'charger',
                'catalog',
                'catalogue',
                'model',
                'vehicle',
                'ev'
            ],

            'price' => [
                'price',
                'cost',
                'rate',
                'quotation',
                'quote',
                'kitne',
                'price kya hai'
            ],

            'warranty' => [
                'warranty',
                'guarantee',
                'replacement'
            ],

            'emi' => [
                'emi',
                'finance',
                'loan',
                'installment'
            ],

            'dealer' => [
                'dealer',
                'dealership',
                'showroom',
                'store',
                'branch'
            ],

            'human' => [
                'executive',
                'agent',
                'human',
                'call',
                'phone',
                'contact',
                'representative'
            ],

            'complaint' => [
                'complaint',
                'issue',
                'problem',
                'not working',
                'service'
            ],

            'greeting' => [
                'hi',
                'hello',
                'hey'
            ]

        ];

        foreach ($intents as $intent => $keywords) {

            foreach ($keywords as $keyword) {

                if (str_contains($text, $keyword)) {
                    return $intent;
                }

            }

        }

        return 'unknown';
    }
}
