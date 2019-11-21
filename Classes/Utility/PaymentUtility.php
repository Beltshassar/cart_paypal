<?php

namespace imhlab\CartQuickPay\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use QuickPay\QuickPay;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentUtility
{

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->configurationManager = $this->objectManager->get(
            ConfigurationManager::class
        );

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartQuickPay'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }



    /**
     * @param array $params
     *
     * @return array
     */
    public function handlePayment(array $params): array
    {

        $this->orderItem = $params['orderItem'];

        list($provider, $type, $brand) = array_map('trim', explode('-', $this->orderItem->getPayment()->getProvider()));

        if ($provider === 'QUICKPAY') {
            $params['providerUsed'] = true;

            try {
                //Initialize client
                $client = new QuickPay(":api-key-here");
            
                //Create payment
                $payment = $client->request->post('/payments', [
                    'order_id' => $this->orderItem->getOrderNumber(),
                    'currency' => 'DKK',
                ]);
            
                $status = $payment->httpStatus();
            
                //Determine if payment was created successfully
                if ($status === 201) {
            
                    $paymentObject = $payment->asObject();
            
                    //Construct url to create payment link
                    $endpoint = sprintf("/payments/%s/link", $paymentObject->id);
            
                    //Issue a put request to create payment link
                    $link = $client->request->put($endpoint, [
//                        'amount' => 100 //amount in cents
                        'amount' => $this->orderItem->getTotalGross()
                    ]);
            
                    //Determine if payment link was created succesfully
                    if ($link->httpStatus() === 200) {
                        //Get payment link url
                        echo $link->asObject()->url;
                    }
                }
            } catch (\Exception $e) {
                echo $e->getMessage();
            }

        }

    }



    /**
     * @param array $params
     *
     * @return array
     */
    /*
    public function handlePayment(array $params): array
    {
        $this->orderItem = $params['orderItem'];

        list($provider, $type, $brand) = array_map('trim', explode('-', $this->orderItem->getPayment()->getProvider()));

        if ($provider === 'QUICKPAY') {
            $params['providerUsed'] = true;

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($params['cart']);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                CartRepository::class
            );
            $cartRepository->add($cart);
            $this->persistenceManager->persistAll();

            $quickpay = new QuickPay(
                $this->conf['merchantId'],
                $this->conf['soapPassword'],
                $this->conf['test'],
                $this->conf['enableDebug'],
                null,
                null,
                null,
                null,
                null,
                $this->conf['enableDebugCurl'],
                null,
                null,
                null,
                'quickpay.log',
                \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log',
                'quickpay_curl.log'
            );

            $mdxi = new QuickpayOrder();
            $mdxi->Order->Tid = $this->orderItem->getOrderNumber();

            if (!empty($type)) {
                $mdxi->Order->PaymentTypes->setEnable('true');
                $mdxi->Order->PaymentTypes->Payment(1)->setType($type);
                if (!empty($brand)) {
                    $mdxi->Order->PaymentTypes->Payment(1)->setBrand($brand);
                }
            }
            $mdxi->Order->Price = $this->orderItem->getTotalGross();

            $mdxi->Order->URL->Success = $this->getUrl('success', $cart->getSHash());
            $mdxi->Order->URL->Error = $this->getUrl('cancel', $cart->getFHash());
            $mdxi->Order->URL->Confirmation = $this->getUrl('confirm', $cart->getSHash());

            $paymentPageURL = $quickpay->paymentPage($mdxi)->getLocation();

            header('Location: ' . $paymentPageURL);
        }

        return [$params];
    }

*/

    /**
     * Builds a return URL to Cart order controller action
     *
     * @param string $action
     * @param string $hash
     *
     * @return string
     */
    protected function getUrl($action, $hash): string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartquickpay_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);

        return $uriBuilder;
    }
}