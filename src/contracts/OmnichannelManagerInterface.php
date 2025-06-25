<?php
namespace src\contracts;

interface OmnichannelManagerInterface{
    //ENCONTRA A PROPOSTA NO PLOOMES
    public function requestQuote(object $deal):array|null;
    //ENCONTRA O CNPJ DO CLIENTE NO PLOOMES
    public function contactCnpj(object $deal):string;
    //ENCONTRA O EMAIL DO VENDEDOR NO PLOOMES
    public function ownerMail(object $deal):string;
    //encontra a venda no ploomes
    public function requestOrder(object $deal):array|null;
    //cria uma Interação no ploomes
    public function createPloomesIteraction(string $json):bool;
    //ENCONTRA UM CLIENTE NO PLOOMES ATRAVÉS DO CNPJ
    public function consultaClientePloomesCnpj(string $cnpj);
    public function userGetOne(string $id);
}