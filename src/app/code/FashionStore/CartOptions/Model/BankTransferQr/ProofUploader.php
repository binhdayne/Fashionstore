<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\BankTransferQr;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProofUploader
{
    private const TARGET_PATH = 'fashionstore/payment-proof';

    private UploaderFactory $uploaderFactory;

    private Filesystem $filesystem;

    private StoreManagerInterface $storeManager;

    public function __construct(
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
    }

    public function save(string $fileId): string
    {
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $targetAbsolutePath = $mediaDirectory->getAbsolutePath(self::TARGET_PATH);

        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp']);
        $uploader->setAllowCreateFolders(true);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $result = $uploader->save($targetAbsolutePath);
        if (!is_array($result) || empty($result['file'])) {
            throw new LocalizedException(__('Unable to save payment proof image.'));
        }

        return self::TARGET_PATH . '/' . ltrim((string) $result['file'], '/');
    }

    public function getFileUrl(string $relativePath): string
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . ltrim($relativePath, '/');
    }
}