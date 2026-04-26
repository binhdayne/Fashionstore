<?php
require 'app/bootstrap.php';
$app = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getApplication();
$objectManager = $app->getObjectManager();
$methodList = $objectManager->create(\Magento\Payment\Model\MethodList::class);
$quote = $objectManager->create(\Magento\Quote\Model\Quote::class)->loadByIdWithoutStore(169);

echo "Available Payment Methods for Quote 169:\n";
$methods = $methodList->getAvailableMethods($quote);
foreach ($methods as $m) {
    echo "- " . $m->getCode() . " (" . $m->getTitle() . ")\n";
}
echo "\nTotal: " . count($methods) . "\n";
