<?php
namespace src\contracts;

interface ErpFormattersInterface
{
    public function findAction(array $args):array;

    public function createOrder(object $orderData, object $credentials): string;
    public function createObjectCrmContactFromErpData(array $clientData): object;
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string;
    public function createObjectErpClientFromCrmData(array $contactData, object $ploomesServices):object;
    public function updateContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array;
    public function createContactCRMToERP(object $contact, object $ploomesServices, object $tenant):array;
    public function createContactERP(string $json, object $ploomesServices):array;
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array;
    public function createOrderErp(string $jsonPedido):array;
    public function getIdVendedorERP(object $omie, string $mailVendedor):string|null;
}