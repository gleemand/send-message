<?php

namespace App\Action;

use App\Service\CrmApiService;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class SendMessageAction
{
    private ContainerBagInterface $params;

    private CrmApiService $crmApi;

    private LoggerInterface $logger;

    public function __construct(ContainerBagInterface $params, CrmApiService $crmApi, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->crmApi = $crmApi;
        $this->logger = $logger;
    }

    public function runPlease()
    {
        $result = true;

        $dateFields = $this->params->get('crm.date_fields');

        foreach ($dateFields as $dateField => $timeField) {
            $isSuccess = $this->processFilteredOrders($dateField, $timeField);

            if (!$isSuccess) {
                $result = false;
            }
        }

        return $result;
    }

    private function processFilteredOrders(string $dateField, string $timeField)
    {
        $result = true;

        $tomorrowDate = new DateTime('tomorrow');
        $this->logger->debug(
            __METHOD__ . ': '
            . '$tomorrowDate: '
            . $tomorrowDate->format('Y-m-d')
            . ', $dateField: '
            . $dateField
        );

        // Generator:
        $orders = $this->crmApi->getOrders($tomorrowDate, $dateField);

        if (!$orders) {
            return false;
        }

        foreach ($orders as $order) {
            $isSuccess = $this->processOrder($order, $dateField, $timeField);

            if (!$isSuccess) {
                $result = false;
            }
        }

        return $result;
    }

    private function processOrder($order, $dateField, $timeField)
    {
        $stateField = $this->params->get('crm.state_field');
        $messageState = $order->customFields[$stateField] ?? null;

        $this->logger->info(__METHOD__ . ': ' . 'order: ' . $order->id . ', state: ' . $messageState);

        if ('new' === $messageState) {
            $date = new DateTime($order->customFields[$dateField]);
            $date = $date->format('d-m-Y');
            $time = substr_replace($order->customFields[$timeField], ':', -2, 0);

            return $this->sendMessage($order, $date . '|' . $time);
        }

        return false;
    }

    /*
     * Mean to call trigger by changing special field to special value :)
     */
    private function sendMessage($order, $dateAndTime)
    {
        $this->logger->info(__METHOD__ . ': ' . 'order: ' . $order->id . ', $dateAndTime: ' . $dateAndTime);

        return $this->crmApi->setStateToOrder($order, 'send|' . $dateAndTime);
    }
}