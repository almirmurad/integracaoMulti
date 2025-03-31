<?php
namespace src\contracts;

interface OmieManagerInterface{
    //BUSCA O ID DO CLIENTE OMIE
    public function clienteIdOmie(object $omie, string $contactCnpj);
    //BUSCA O VENDEDOR OMIE 
    public function vendedorIdOmie(object $omie, string $mailVendedor);
    //BUSCA ID DO PRODUTO NO OMIE
    public function buscaIdProductOmie(object $omie, string $idItem);
    //CRIA O PEDIDO NO OMIE 
    public function criaPedidoOmie(string $json);
    //CRIA O SERVIÇO NO OMIE 
    public function criaOS(object $omie, object $os, array $structureOS);
    //ENCONTRA O CNPJ DO CLIENTE NO OMIE
    public function clienteCnpjOmie(object $omie);
    //ENCONTRA O PEDIDO ATRAVÉS DO ID DO OMIE
    public function consultaPedidoOmie(object $omie, int $idPedido);
    //CONSULTA NOTA FISCAL NO OMIE
    public function consultaNotaOmie(object $omie, int $idPedido );
    
}