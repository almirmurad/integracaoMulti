<?php

namespace src\handlers;

use src\models\Deal;
use src\models\Homologacao_invoicing;
use src\models\Homologacao_order;
use src\models\Manospr_invoicing;
use src\models\Manospr_order;
use src\models\Manossc_invoicing;
use src\models\Manossc_order;
use src\models\User;

class DashboardHandler
{
    
    public static function getAllTotals()
    {   
        $t =[];

        $t['totalDeals']    = Deal::select()->count();
        $t['totalInvoicesHML'] = Homologacao_invoicing::select()->count();
        $t['totalInvoicesMPR'] = Manospr_invoicing::select()->count();
        $t['totalInvoicesMSC'] = Manossc_invoicing::select()->count();
        $t['totalInvoices'] = $t['totalInvoicesHML'] + $t['totalInvoicesMPR'] + $t['totalInvoicesMSC'];
        $t['totalCanceledInvoicesHML'] = Homologacao_invoicing::select()->where('is_canceled', 1)->count();
        $t['totalCanceledInvoicesMPR'] = Manospr_invoicing::select()->where('is_canceled', 1)->count();
        $t['totalCanceledInvoicesMSC'] = Manossc_invoicing::select()->where('is_canceled', 1)->count();
        $t['totalCanceledInvoices'] = $t['totalCanceledInvoicesHML'] + $t['totalCanceledInvoicesMPR'] + $t['totalCanceledInvoicesMSC'];
        $t['totalCanceledOrdersHML'] = Homologacao_order::select()->where('is_canceled', 1)->count();
        $t['totalCanceledOrdersMPR'] = Manospr_order::select()->where('is_canceled', 1)->count();
        $t['totalCanceledOrdersMSC'] = Manossc_order::select()->where('is_canceled', 1)->count();
        $t['totalCanceledOrders'] = $t['totalCanceledOrdersHML'] + $t['totalCanceledOrdersMPR'] + $t['totalCanceledOrdersMSC'];
        $t['totalUsers']    = User::select()->count();
        $t['totalOmieOrdersHML']    = Homologacao_order::select()->count();
        $t['totalOmieOrdersMPR']    = Manospr_order::select()->count();
        $t['totalOmieOrdersMSC']    = Manossc_order::select()->count();
        $t['totalOmieOrders']    = $t['totalOmieOrdersHML'] + $t['totalOmieOrdersMPR'] + $t['totalOmieOrdersMSC'];

        $totals = json_encode($t);

        return $totals;
    }
}