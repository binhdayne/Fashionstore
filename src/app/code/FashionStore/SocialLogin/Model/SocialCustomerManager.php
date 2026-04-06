<?php
namespace FashionStore\SocialLogin\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;

class SocialCustomerManager
{
    /**
     * @var SocialAccountRepository
     */
    private $socialAccountRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerInterfaceFactory
     */
    private $customerFactory;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Random
     */
    private $random;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        SocialAccountRepository $socialAccountRepository,
        CustomerRepositoryInterface $customerRepository,
        CustomerInterfaceFactory $customerFactory,
        AccountManagementInterface $accountManagement,
        StoreManagerInterface $storeManager,
        Random $random,
        Config $config
    ) {
        $this->socialAccountRepository = $socialAccountRepository;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->accountManagement = $accountManagement;
        $this->storeManager = $storeManager;
        $this->random = $random;
        $this->config = $config;
    }

    /**
     * @param array<string, string> $profile
     */
    public function resolveCustomer(string $provider, array $profile, ?CustomerInterface $currentCustomer = null): CustomerInterface
    {
        $providerUserId = trim((string) ($profile['provider_user_id'] ?? ''));
        if ($providerUserId === '') {
            throw new LocalizedException(__('The social provider did not return a valid user identifier.'));
        }

        $linkedAccount = $this->socialAccountRepository->getByProviderUserId($provider, $providerUserId);
        if ($linkedAccount !== null) {
            return $this->customerRepository->getById((int) $linkedAccount['customer_id']);
        }

        if ($currentCustomer !== null && $currentCustomer->getId()) {
            $customer = $this->customerRepository->getById((int) $currentCustomer->getId());
        } else {
            $customer = $this->findOrCreateCustomer($provider, $profile);
        }

        $this->socialAccountRepository->saveLink(
            (int) $customer->getId(),
            $provider,
            $providerUserId,
            trim((string) ($profile['email'] ?? ''))
        );

        return $this->customerRepository->getById((int) $customer->getId());
    }

    /**
     * @param array<string, string> $profile
     */
    private function findOrCreateCustomer(string $provider, array $profile): CustomerInterface
    {
        $email = trim((string) ($profile['email'] ?? ''));
        if ($email !== '') {
            try {
                return $this->customerRepository->get($email, (int) $this->storeManager->getWebsite()->getId());
            } catch (NoSuchEntityException $exception) {
            }
        }

        if ($email === '') {
            throw new LocalizedException(__('Your %1 account did not return an email address. Please allow email sharing or sign in with email first.', $this->config->getProviderLabel($provider)));
        }

        $store = $this->storeManager->getStore();
        $name = $this->resolveName($provider, $profile);
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId((int) $store->getWebsiteId());
        $customer->setStoreId((int) $store->getId());
        $customer->setEmail($email);
        $customer->setFirstname($name['firstname']);
        $customer->setLastname($name['lastname']);

        return $this->accountManagement->createAccount($customer, $this->random->getRandomString(32));
    }

    /**
     * @param array<string, string> $profile
     * @return array<string, string>
     */
    private function resolveName(string $provider, array $profile): array
    {
        $firstname = trim((string) ($profile['firstname'] ?? ''));
        $lastname = trim((string) ($profile['lastname'] ?? ''));
        $fullName = trim((string) ($profile['name'] ?? ''));

        if ($firstname !== '' && $lastname !== '') {
            return [
                'firstname' => $firstname,
                'lastname' => $lastname,
            ];
        }

        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            if (count($parts) > 1) {
                $firstname = array_shift($parts) ?: $firstname;
                $lastname = implode(' ', $parts) ?: $lastname;
            } elseif ($parts !== []) {
                $firstname = $parts[0];
            }
        }

        if ($firstname === '') {
            $firstname = $this->config->getProviderLabel($provider);
        }

        if ($lastname === '') {
            $lastname = 'User';
        }

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];
    }
}