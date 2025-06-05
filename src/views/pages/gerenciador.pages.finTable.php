<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <style type="text/css">
    *{
      margin: 0;
      padding: 0;
    }
td, th, tr
{
font-family: Trebuchet MS, Helvetica, sans-serif;
font-size:11px;
padding:10px !important;
text-align:center;
vertical-align:middle;
}
th
{
background-color: #33A0FF;
}
.table-wrapper
{
overflow-x:auto;
max-width:100%;
}
.titulo-a-vencer{
  /* background-color: #A4FACF; */
  color: #2FD180;
}
.titulo-pago{
  /* background-color: #BDE0FF; */
  color: #409AE9;
}
.titulo-vencido, .previsaoDeSaida{
  /* background-color: #FBC1C1; */
  color: #F66F6F;
}
</style>
</head>
<body>
<table cellpadding="3">
<tbody>
  <tr>
    <th style="min-width: 150px;"><span style="color:#fff">Parcelas</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Status</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Origem</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Data Registro</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Data Emissão</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Data Previsão</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Data Vencimento</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Valor</span></th>
    <th style="min-width: 150px;"><span style="color:#fff">Observação</span></th>
  </tr>
  {tr}

</tbody>
</table>

<p>Última atualização: {data}</p>
</body>
</html>