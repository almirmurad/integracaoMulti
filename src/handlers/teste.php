<?php

$array = [
  'app_key' =>  4194053472609,
  'app_secret' => '43cf1d9c1d63974acf152aeeab8777ef',
  'call' => 'ExcluirProjeto',
  'param'=>[
      [
          'codigo'=> 7012732763
      ]
  ],
];
$json = json_encode($array);
print_r($json);
exit;
    //function index padrão dos controllers, a princípio desnecessário
    // public function index() {
    //     //$total = Deal::select('id')->count();        
    //     $data = [
    //         'pagina' => 'Pedidos',
    //         'loggedUser'=>$this->loggedUser,
    //         //'total'=>$total
    //     ];
    //     $this->render('gerenciador.pages.index', $data);
    // }

     //REPROCESSA O WEBHOOK COM FALHA não esta sendo usado no momento
    // public function reprocessWebhook($hook){
    //     $status = 4;//falhou
    //     //$hook = $this->databaseServices->getWebhook($status, 'Contacts');
    //     //$json = $hook['json'];
    //     $status = 2; //processando
    //     $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
        
    //     if($alterStatus){
            
    //         $createClient = Self::newClient($hook);
            
            
    //         if(!isset($createClient['contactsCreate']['error'])){
    //             $status = 3; //Sucesso
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
    //             if($alterStatus){
    //                 return $createClient;//card processado pedido criado no Omie retorna mensagem winDeal para salvr no log
    //             }

    //         }else{
    //             $status = 4; //falhou com mensagem
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($hook['id'], $status);
                
    //             return $createClient;
    //         }
    //     }
        
    // }

      //inclui o cliente do omie na tabela clientes para futuro dashboard
    // if($criaClienteOmie['codigo_cliente_omie']){
    //     //salva um deal no banco
    //     $deal->omieOrderId = $incluiPedidoOmie['codigo_pedido'];
    //     $dealCreatedId = $this->databaseServices->saveDeal($deal);   
    //     $message['winDeal']['dealMessage'] ='Id do Deal no Banco de Dados: '.$dealCreatedId;  
    //     if($dealCreatedId){

    //         $omie[$k]->idOmie = $deal->omieOrderId;
    //         $omie[$k]->codCliente = $idClienteOmie;
    //         $omie[$k]->codPedidoIntegracao = $deal->lastOrderId;
    //         $omie[$k]->numPedidoOmie = intval($incluiPedidoOmie['numero_pedido']);
    //         $omie[$k]->codClienteIntegracao = $deal->contactId;
    //         $omie[$k]->dataPrevisao = $deal->finishDate;
    //         $omie[$k]->codVendedorOmie = $codVendedorOmie;
    //         $omie[$k]->idVendedorPloomes = $deal->ownerId;   
    //         $omie[$k]->appKey = $omie[$k]->appKey;             
    
    //         $id = $this->databaseServices->saveOrder($omie[$k]);
    //         $message['winDeal']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos '.$omie[$k]->baseFaturamentoTitle.' id '.$id.'em: '.$current;
    //     }
        
    // }
?>
<!--
<!DOCTYPE html>
 <html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <style type="text/css">
td, th, tr
{
font-family: Trebuchet MS, Helvetica, sans-serif;
font-size:11px;
padding:10px;
text-align:center;
vertical-align:middle;
}
th
{
background-color: rgb(238, 238, 238);
border-bottom: 2px #ccc solid !important;
}
.table-wrapper
{
overflow-x:auto;
max-width:100%;
}
</style>
</head>
<body>
<table cellpadding="3">
<tbody>
  <tr>
    <th style="min-width: 60px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Status do Estoque</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Local de Estoque</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Disponível</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Mínimo</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Previsão de Saída</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Tipo de Local de Estoque</span></th>
  </tr>
  <tr id="4162600097">
    <td class="statusDeEstoque">🟢 :U+1F7E2 Ativo</td>
    <td class="localDeEstoque">PADRAO - Local de Estoque Padrão</td>
    <td>0</td>
    <td>0</td>
    <td class="previsaoDeSaida">0</td>
    <td class="tipoDeLocalDeEstoque">Estoque próprio da empresa</td>
  </tr>
</tbody>
</table>

<p>Última atualização: 29/08/2024 06:42</p>
</body>
</html>


🔴 Propostas perdidas -->