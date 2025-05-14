<?php
namespace src\contracts;

interface ErpManagerInterface{
    //BUSCA O ID DO CLIENTE ERP
    public function clientIdErp(object $erp, string $contactCnpj);
    //BUSCA O VENDEDOR ERP 
    public function vendedorIdErp(object $erp, string $mailVendedor);
    //BUSCA ID DO PRODUTO NO ERP
    public function buscaIdProductErp(object $erp, string $idItem);
    //CRIA O PEDIDO NO ERP 
    public function criaPedidoErp(string $json);
    //CRIA O SERVIÇO NO ERP 
    public function criaOSErp(object $erp, object $os, array $structureOS);
    //ENCONTRA O CNPJ DO CLIENTE NO ERP
    public function clienteCnpjErp(object $erp);
    //ENCONTRA O PEDIDO ATRAVÉS DO ID DO ERP
    public function consultaPedidoErp(object $erp, int $idPedido);
    //CONSULTA NOTA FISCAL NO ERP
    public function consultaNotaErp(object $erp, int $idPedido );   
    //CRIA CLIENTE NO ERP
    public function criaClienteERP(string $json);
}