<?php
namespace src\contracts;

use src\services\PloomesServices;

interface ErpFormattersInterface
{
    public function createOrder(object $orderData, object $credentials): string;
    public function createObjectCrmContactFromErpData(array $clientData): object;
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string;
    public function createObjectErpClientFromCrmData(array $contactData, PloomesServices $ploomesServices):object;
    public function updateContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array;
    public function createContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array;
    public function createContactERP(string $json, object $ploomesServices):array;
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array;
    public function createOrderErp(string $jsonPedido):array;
    public function getIdVendedorERP(object $erp, string $mailVendedor):string|null;
}