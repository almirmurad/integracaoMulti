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
    <!-- <th style="min-width: 60px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Status do Estoque</span></th> -->
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Local de Estoque</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Saldo de Estoque</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Mínimo</span></th>
    <th style="min-width: 75px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Pendente</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Reservado</span></th>
    <th style="min-width: 150px; background-color: rgb(238, 238, 238);"><span style="color:#2c3e50">Estoque Físico</span></th>
  </tr>
  <tr id="4162600097">
    <!-- <td class="statusDeEstoque">🟢 :U+1F7E2 Ativo</td> -->
    <td class="localDeEstoque">{local}</td>
    <td>{saldo}</td>
    <td>{minimo}</td>
    <td class="previsaoDeSaida">{pendente}</td>
    <td class="tipoDeLocalDeEstoque">{reservado}</td>
    <td class="tipoDeLocalDeEstoque">{fisico}</td>
  </tr>
</tbody>
</table>

<p>Última atualização: {data}</p>
</body>
</html>