<?php

namespace FashionStore\Review\Console\Command;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedReviewsCommand extends Command
{
    private const REVIEWS_PER_PRODUCT = 10;

    private const NICKNAMES = [
        'Lan Anh',
        'Minh Chau',
        'Thu Ha',
        'Ngoc Mai',
        'Bao Tran',
        'Quynh Nhu',
        'Hoang Linh',
        'Thao Nguyen',
        'Gia Han',
        'Thanh Truc',
    ];

    private const SUMMARIES = [
        'San pham tuyet voi',
        'Dang tien mua',
        'Mac len rat dep',
        'Chat luong vuot mong doi',
        'Se quay lai ung ho',
        'Rat hai long',
        'Form len chuan',
        'Chat vai dep',
    ];

    private const DETAILS = [
        'San pham tuyet voi, duong may chac chan va len form rat dep.',
        'Chat luong vai tot, mac thoai mai va dung nhu mo ta.',
        'Giao hang nhanh, dong goi can than, trai nghiem rat tot.',
        'Form chuan, mau sac dep, mac di lam hay di choi deu hop.',
        'Chat lieu mem, de phoi do, gia tri nhan duoc rat on.',
        'San pham dep hon ca hinh, rat dang de mua lai.',
        'Mac ton dang, cam giac de chiu suot ca ngay.',
        'Hoan thien tot, khong co chi tiet loi, rat hai long.',
    ];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ReviewFactory $reviewFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('fashionstore:review:seed')
            ->setDescription('Seed 10 approved fake reviews for every active product');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = (int) $this->storeManager->getDefaultStoreView()->getId();
        $ratingOptions = $this->getRatingOptionsByValue($storeId);

        if ($ratingOptions === []) {
            throw new LocalizedException(__('No active product rating options were found.'));
        }

        $pageSize = 100;
        $currentPage = 1;
        $productCount = 0;
        $reviewCount = 0;

        do {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['name']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($currentPage);
            $collection->setOrder('entity_id', 'ASC');

            $items = $collection->getItems();
            if ($items === []) {
                break;
            }

            foreach ($items as $product) {
                $reviewCount += $this->seedReviewsForProduct($product, $storeId, $ratingOptions);
                ++$productCount;
            }

            $collection->clear();
            ++$currentPage;
        } while (true);

        $output->writeln('<info>Review seeding completed.</info>');
        $output->writeln('<comment>Active products processed: ' . $productCount . '</comment>');
        $output->writeln('<comment>Approved reviews inserted: ' . $reviewCount . '</comment>');

        return Command::SUCCESS;
    }

    private function seedReviewsForProduct(Product $product, int $storeId, array $ratingOptions): int
    {
        $inserted = 0;

        for ($index = 0; $index < self::REVIEWS_PER_PRODUCT; ++$index) {
            $review = $this->reviewFactory->create();
            $review->setData([
                'nickname' => $this->randomValue(self::NICKNAMES),
                'title' => $this->randomValue(self::SUMMARIES),
                'detail' => $this->randomValue(self::DETAILS),
            ]);

            $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue((int) $product->getId())
                ->setStatusId(Review::STATUS_APPROVED)
                ->setStoreId($storeId)
                ->setStores([$storeId])
                ->save();

            foreach ($ratingOptions as $ratingId => $optionsByValue) {
                $stars = random_int(4, 5);
                if (!isset($optionsByValue[$stars])) {
                    continue;
                }

                $this->ratingFactory->create()
                    ->setRatingId($ratingId)
                    ->setReviewId((int) $review->getId())
                    ->addOptionVote((int) $optionsByValue[$stars], (int) $product->getId());
            }

            $review->aggregate();
            ++$inserted;
        }

        return $inserted;
    }

    private function getRatingOptionsByValue(int $storeId): array
    {
        $ratings = $this->ratingFactory->create()
            ->getResourceCollection()
            ->addEntityFilter(Review::ENTITY_PRODUCT_CODE)
            ->setPositionOrder()
            ->addRatingPerStoreName($storeId)
            ->setStoreFilter($storeId)
            ->setActiveFilter(true)
            ->load()
            ->addOptionToItems();

        $ratingOptions = [];
        foreach ($ratings as $rating) {
            $options = [];
            foreach ($rating->getOptions() as $option) {
                $options[(int) $option->getValue()] = (int) $option->getId();
            }

            if ($options !== []) {
                $ratingOptions[(int) $rating->getId()] = $options;
            }
        }

        return $ratingOptions;
    }

    private function randomValue(array $values): string
    {
        return (string) $values[array_rand($values)];
    }
}