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
.previsaoDeSaida{
  background-color:#F08080;
}
.titulo-a-vencer{
  background-color:#90EE90;
}
.titulo-vencido{
  background-color:#F08080;
}
</style>
</head>
<body>
<table cellpadding="3">
<tbody>
  <tr>
    <!-- <th style="min-width: 60px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Status do Estoque</span></th> -->
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Parcelas</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Status</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Origem</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Data Registro</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Data Emissão</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Data Previsão</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Data Vencimento</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Valor</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Observação</span></th>
  </tr>
  {tr}
  <!-- <tr id="4162600097"> -->
    <!-- <td class="statusDeEstoque">🟢 :U+1F7E2 Ativo</td> -->
    <!-- <td class="localDeEstoque">{parcelas}</td>
    <td>{status}</td>
    <td>{origem}</td>
    <td>{registro}</td>
    <td>{emissao}</td>
    <td>{previsao}</td>
    <td class="previsaoDeSaida">{vencimento}</td>
    <td class="tipoDeLocalDeEstoque">{valor}</td>
    <td class="tipoDeLocalDeEstoque">{observacao}</td> -->
  <!-- </tr> -->
</tbody>
</table>

<p>Última atualização: {data}</p>
</body>
</html>