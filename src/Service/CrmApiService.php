<?php

namespace App\Service;

use DateTime;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ClientFactory;
use RetailCrm\Api\Model\Entity\Orders\Order;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Filter\Orders\OrderFilter;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;

class CrmApiService
{
    private ContainerBagInterface $params;

    private Client $client;

    private LoggerInterface $logger;

    public function __construct(
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->params = $params;
        $this->logger = $logger;

        $apiUrl = $this->params->get('crm.api_url');
        $apiKey = $this->params->get('crm.api_key');

        $this->client = SimpleClientFactory::createClient($apiUrl, $apiKey);
    }

    public function getOrders(DateTime $date, string $dateField)
    {
        $stateField = $this->params->get('crm.state_field');

        $request = new OrdersRequest();
        $request->limit = 20;
        $request->page = 1;

        $request->filter = new OrderFilter();
        $request->filter->customFields = [
            $dateField => [
                'min' => $date->format('Y-m-d'),
                'max' => $date->format('Y-m-d'),
            ],
            $stateField => 'new',
        ];

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $response = $this->client->orders->list($request);
            } catch (ApiExceptionInterface $exception) {
                $this->logger->error(__METHOD__ . ': ' . sprintf(
                    'Error from RetailCRM API (status code: %d): %s',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                ));

                if (count($exception->getErrorResponse()->errors) > 0) {
                    $this->logger->error(__METHOD__ . ': ' . 'Errors: ' . implode(
                        ', ',
                        $exception->getErrorResponse()->errors
                    ));
                }

                return false;
            }

            if (empty($response->orders)) {
                break;
            }

            foreach ($response->orders as $order) {
                $this->logger->debug(__METHOD__ . ': ' . 'yield order #' . $order->id);

                yield $order;
            }

            ++$request->page;
        } while ($response->pagination->currentPage < $response->pagination->totalPageCount);
    }

    public function setStateToOrder($processedOrder, $state)
    {
        $stateField = $this->params->get('crm.state_field');

        $order                 = new Order();
        $order->customFields   = [
            $stateField => $state
        ];

        $request        = new OrdersEditRequest();
        $request->by    = ByIdentifier::ID;
        $request->site  = $processedOrder->site;
        $request->order = $order;

        try {
            $response = $this->client->orders->edit($processedOrder->id, $request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(__METHOD__ . ': ' . sprintf(
                    'Error from RetailCRM API (status code: %d): %s',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(__METHOD__ . ': ' . 'Errors: ' . implode(
                        ', ',
                        $exception->getErrorResponse()->errors
                    ));
            }

            return false;
        }

        $this->logger->debug(__METHOD__ . ': ' . 'order: ' . $response->order->id);

        return true;
    }
}