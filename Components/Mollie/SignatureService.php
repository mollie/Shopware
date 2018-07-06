<?php

    namespace MollieShopware\Components\Mollie;

    class SignatureService
    {

        private $internal_service = null;

        private function internal_service()
        {

            if ($this->internal_service !== null){
                return $this->internal_service;
            }

            try{

                $this->internal_service = Shopware()->Container()->get('basket_signature_generator');

            }
            catch(Exception $e){
                return $this->internal_service = false;
            }
            finally{
                return $this->internal_service = false;
            }

        }

        public function generateSignature(array $basket, $customerId)
        {

            if ($service = $this->internal_service()){

                return $service->generateSignature($basket, $customerId);

            }

            $items = array_map(
                function (array $item) {
                    return [
                        'ordernumber' => $item['ordernumber'],
                        'quantity' => (float) $item['quantity'],
                        'tax_rate' => (float) $item['tax_rate'],
                        'price' => (float) $item['price'],
                    ];
                },
                $basket['content']
            );

            $items = $this->sortItems($items);

            $data = [
                'amount' => (float) $basket['sAmount'],
                'taxAmount' => (float) $basket['sAmountTax'],
                'items' => $items,
                'currencyId' => (int) $basket['sCurrencyId'],
            ];

            return hash('sha256', json_encode($data) . $customerId);
        }

        private function sortItems(array $items)
        {
            usort(
                $items,
                function (array $a, array $b) {
                    if ($a['price'] < $b['price']) {
                        return 1;
                    } elseif ($a['price'] > $b['price']) {
                        return -1;
                    }

                    if ($a['quantity'] < $b['quantity']) {
                        return 1;
                    } elseif ($a['quantity'] > $b['quantity']) {
                        return -1;
                    }

                    if ($a['tax_rate'] < $b['tax_rate']) {
                        return 1;
                    } elseif ($a['tax_rate'] > $b['tax_rate']) {
                        return -1;
                    }

                    return strcmp($a['ordernumber'], $b['ordernumber']);
                }
            );

            return array_values($items);
        }

    }

