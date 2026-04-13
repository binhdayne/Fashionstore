<?php
namespace FashionStore\SocialLogin\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class SocialAccountRepository
{
    private const TABLE_NAME = 'fashionstore_social_account';

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByProviderUserId(string $provider, string $providerUserId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->where('provider = ?', $provider)
            ->where('provider_user_id = ?', $providerUserId)
            ->limit(1);

        $row = $connection->fetchRow($select);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByCustomerProvider(int $customerId, string $provider): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->where('customer_id = ?', $customerId)
            ->where('provider = ?', $provider)
            ->limit(1);

        $row = $connection->fetchRow($select);

        return is_array($row) ? $row : null;
    }

    public function saveLink(int $customerId, string $provider, string $providerUserId, ?string $email): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->getTableName();
        $existingByProviderId = $this->getByProviderUserId($provider, $providerUserId);
        if ($existingByProviderId !== null && (int) $existingByProviderId['customer_id'] !== $customerId) {
            throw new LocalizedException(__('This social account is already linked to another customer.'));
        }

        $existingByCustomer = $this->getByCustomerProvider($customerId, $provider);
        $data = [
            'customer_id' => $customerId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email ?: null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($existingByCustomer !== null) {
            $connection->update($tableName, $data, ['entity_id = ?' => (int) $existingByCustomer['entity_id']]);
            return;
        }

        $data['created_at'] = gmdate('Y-m-d H:i:s');
        $connection->insert($tableName, $data);
    }

    private function getTableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE_NAME);
    }
}