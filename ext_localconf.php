<?php
error_log("qp-localconf:2");
defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'imhlab.cart_quickpay',
    'Cart',
    [
        'Order\Payment' => 'confirm, success, cancel',
    ],
    // non-cacheable actions
    [
        'Order\Payment' => 'confirm, success, cancel',
    ]
);

// configure signal slots

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
$dispatcher->connect(
    \Extcode\Cart\Utility\PaymentUtility::class,
    'handlePayment',
    \imhlab\CartQuickPay\Utility\PaymentUtility::class,
    'handlePayment'
);

// exclude parameters from cHash

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'TID';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'LANGUAGE';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'USER_FIELD';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'BRAND';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'ERRTEXT';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'EXTERNALSTATUS';
