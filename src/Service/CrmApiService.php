<?php

namespace App\Service;

use RetailCrm\Api\Interfaces\ClientFactoryInterface;
use RetailCrm\Api\Model\Entity\Customers\CustomerHistory;
use RetailCrm\Api\Model\Entity\Orders\OrderHistory;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Model\Filter\Orders\OrderHistoryFilterV4Type;
use RetailCrm\Api\Model\Request\Orders\OrdersHistoryRequest;

class HistoryService
{
    private ContainerBagInterface $params;

    private ClientFactoryInterface $client;

    private SinceIdService $sinceId;

    public function __construct(ContainerBagInterface $params, ClientFactoryInterface $client, SinceIdService $sinceId)
    {
        $this->params = $params;
        $this->client = $client;
        $this->sinceId = $sinceId;

        $this->client->createClient($this->params->get('crm.url'), $this->params->get('crm.api_key'));
    }

    public function getHistory()
    {
        $request                  = new OrdersHistoryRequest();
        $request->limit           = 100;
        $request->page            = 1;
        $request->filter          = new OrderHistoryFilterV4Type();
        $request->filter->sinceId = $this->sinceId->getSinceId();

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $response = $this->client->orders->history($request);
            } catch (ApiExceptionInterface $exception) {
                echo sprintf(
                    'Error from RetailCRM API (status code: %d): %s',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                );

                return;
            }

            if (empty($response->history)) {
                break;
            }

            foreach ($response->history as $change) {
                if ($this->filterHistory($change)) {
                    yield $change;
                }
            }

            $newSinceId = end($response->history)->id;
            $request->filter->sinceId = $newSinceId;
            $this->sinceId->setSinceId($newSinceId);
        } while ($response->pagination->currentPage < $response->pagination->totalPageCount);
    }

    private function filterHistory($change): bool
    {
        return !$change->deleted
            && (
                in_array($change->field, array_merge([
                    $this->params->get('crm.date_field'),
                    $this->params->get('crm.time_fields'),
                    $this->params->get('crm.tracked_fields')
                ]), true)
                || $change->created
            );
    }
}