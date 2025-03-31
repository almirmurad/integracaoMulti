<?php
namespace src\contracts;

interface ErpFormattersInterface
{
    public function createOrder(object $orderData, object $credentials): string;
    public function createClientErpToCrmObj(array $clientData): object;
    public function createPloomesContactFromErpObject(object $contact, object $ploomesServices): string;
    public function createContactObjFromPloomesCrm(array $contactData, object $ploomesServices):object;
    public function updateContactCRMToERP(object $contact, object $ploomesServices):array;
    public function createContact(object $contact, object $ploomesServices):array;
    public function createContactERP(string $json, object $ploomesServices):array;
    public function updateContactERP(string $json, object $contact, object $ploomesServices):array;
}