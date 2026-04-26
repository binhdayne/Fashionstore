<?php
declare(strict_types=1);

namespace FashionStore\PriceFilterFix\Plugin;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\RequestInterface;

class StrictPriceRangeFilterPlugin
{
    private const MIN_POSSIBLE_PRICE = 0.01;

    /**
     * Enforce the selected price interval directly on the SQL expression used for final price.
     * This guarantees that products outside the shown interval cannot leak into the result.
     *
     * @param object $subject
     * @param mixed $result
     * @param RequestInterface $request
     * @return mixed
     */
    public function afterApply($subject, $result, RequestInterface $request)
    {
        $filterValue = $request->getParam($subject->getRequestVar());
        if (!is_string($filterValue) || $filterValue === '') {
            return $result;
        }

        $intervalToken = explode(',', $filterValue)[0] ?? '';
        $interval = explode('-', $intervalToken, 2);
        if (count($interval) !== 2) {
            return $result;
        }

        [$fromRaw, $toRaw] = $interval;
        if ($fromRaw === '' && $toRaw === '') {
            return $result;
        }
        if ($fromRaw !== '' && !is_numeric($fromRaw)) {
            return $result;
        }
        if ($toRaw !== '' && !is_numeric($toRaw)) {
            return $result;
        }

        $from = $fromRaw === '' ? null : (float) $fromRaw;
        $to = $toRaw === '' ? null : (float) $toRaw;

        if ($from !== null && $to !== null && $from == $to) {
            $to += self::MIN_POSSIBLE_PRICE;
        }

        $collection = $subject->getLayer()->getProductCollection();
        if (!$collection instanceof ProductCollection) {
            return $result;
        }

        $this->applyStrictRangeToCollection($collection, $from, $to);

        return $result;
    }

    private function applyStrictRangeToCollection(ProductCollection $collection, ?float $from, ?float $to): void
    {
        $select = $collection->getSelect();
        $priceExpression = $collection->getPriceExpression($select);
        if (!is_string($priceExpression) || $priceExpression === '') {
            return;
        }

        $additionalExpression = $collection->getAdditionalPriceExpression($select);
        $fullExpression = empty($additionalExpression)
            ? $priceExpression
            : sprintf('(%s %s)', $priceExpression, $additionalExpression);

        $currencyRate = (float) $collection->getCurrencyRate();
        if ($currencyRate <= 0) {
            $currencyRate = 1.0;
        }

        if ($from !== null) {
            $select->where($fullExpression . ' >= ?', $this->getComparingValue($from, $currencyRate));
        }

        if ($to !== null) {
            $select->where($fullExpression . ' < ?', $this->getComparingValue($to, $currencyRate));
        }
    }

    private function getComparingValue(float $price, float $currencyRate): float
    {
        return ($price - self::MIN_POSSIBLE_PRICE / 2) / $currencyRate;
    }
}
