<?php


namespace MollieShopware\Components\Helpers;

class MollieLineItemCleaner
{

    /**
     * Sometimes the advanced promotion suite leads to
     * duplicate discount entries? To avoid that problem
     * this function can easily remove duplicate entries.
     * This will lead to a correct mollie api request and avoid the
     * problem that the amount of line items does not match the
     * total sum of the order.
     *
     * @param array $orderlines
     * @return array
     */
    public function removeDuplicateDiscounts(array $orderlines)
    {
        $newLines = [];
        $cachedDiscountIDs = [];
        
        /** @var array $line */
        foreach ($orderlines as $line) {
            if ($line['type'] !== 'discount') {
                $newLines[] = $line;
                continue;
            }

            # only add our discounts once.
            # the identifier is the name + price.
            $id = $line['name'] . $line['unitPrice']['value'];

            if (!in_array($id, $cachedDiscountIDs)) {
                $newLines[] = $line;
                $cachedDiscountIDs[] = $id;
            }
        }
        
        return $newLines;
    }
}
