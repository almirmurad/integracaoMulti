<div class="wrap">
    <?php $render('gerenciador.partials.head');?>
    <?php $render('gerenciador.partials.header',['loggedUser'=>$loggedUser]);?>
    <main>
    <?php $render('gerenciador.partials.aside',['loggedUser'=>$loggedUser]);?>
    
        <section>
        <div class="title">
            <div class="area-title">
                <h2>Você está em: <?=$pagina?></h2>
            </div>
            <div class="area-filtro">
                <span>Integrações:</span>
                <a class="button" href=""><span>Filtrar</span><i class="material-icons btn">more_vert</i></a>
                <!-- <span>Canais:</span>
                <a class="button" href=""><span>Filtrar</span><i class="material-icons btn">more_vert</i></a> -->
            </div>
        </div>
                <div class="content  ">
                    <?php if(!empty($flash)):?>
                        <div class="area-flash flex">
                            <i class="material-symbols-outlined">
                            warning
                            </i>
                            <h4 class="text-flash">
                                <?=$flash;?>
                            </h4>
                        </div>
                    <?php endif;?>

                    <div class="row-content flex">

                        <!-- Início infoBox -->
                        <div class="info-Box" id="deals">
                            <div class="title">
                                <h2>Propostas Integradas</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de propostas integradas pelo sistema</span>
                                    <div class="total-geral flex">
                                        <!-- <div class="desc-total flex center-center ">HML <span class="flex center-center" id="totalMHL">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="totalMPR">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="totalMSC">?</span></div> -->
                                    </div>
                                    <a href="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->

                        <!-- Início infoBox -->
                        <div class="info-Box" id="users">
                            <div class="title">
                                <h2>Usuários</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de usuários do sistema</span>
                                    <div class="total-geral flex">
                                        <!-- <div class="desc-total flex center-center ">HML <span class="flex center-center" id="#">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="#">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="#">?</span></div> -->
                                    </div>
                                    <a href="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->
                        
                    </div>
                    <div class="row-content flex ">

                        <!-- Início infoBox -->
                        <div class="info-Box" id="omieOrders">
                            <div class="title">
                                <h2>Pedidos no Omie ERP</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de pedidos criados no Omie ERP</span>
                                    <div class="total-geral flex">
                                        <div class="desc-total flex center-center ">HML <span class="flex center-center" id="totalOmieOrdersMHL">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="totalOmieOrdersMPR">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="totalOmieOrdersMSC">?</span></div>
                                    </div>
                                    <a href="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->
                        <!-- Início infoBox -->
                        <div class="info-Box" id="invoices">
                            <div class="title">
                                <h2>Notas Integradas</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de Notas Integradas</span>
                                    <div class="total-geral flex">
                                        <div class="desc-total flex center-center ">HML <span class="flex center-center" id="totalInvoicesMHL">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="totalInvoicesMPR">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="totalInvoicesMSC">?</span></div>
                                    </div>
                                    <a href="" class="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->
                        <!-- Início infoBox -->
                        <div class="info-Box" id="canceledInvoices">
                            <div class="title">
                                <h2>Notas Canceladas</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de Notas Canceladas</span>
                                    <div class="total-geral flex">
                                        <div class="desc-total flex center-center ">HML <span class="flex center-center" id="totalCanceledInvoicesMHL">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="totalCanceledInvoicesMPR">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="totalCanceledInvoicesMSC">?</span></div>
                                    </div>
                                    <a href="" class="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->
                        <!-- Início infoBox -->
                        <div class="info-Box" id="canceledOrders">
                            <div class="title">
                                <h2>Pedidos Cancelados</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <h3>?</h3>
                                    <span>Total de Pedidos Cancelados</span>
                                    <div class="total-geral flex">
                                        <div class="desc-total flex center-center ">HML <span class="flex center-center" id="totalCanceledOrdersMHL">?</span></div>
                                        <div class="desc-total flex center-center ">MPR <span class="flex center-center" id="totalCanceledOrdersMPR">?</span></div>
                                        <div class="desc-total flex center-center ">MSC <span class="flex center-center" id="totalCanceledOrdersMSC">?</span></div>
                                    </div>
                                    <a href="" class="">Ver detalhes da integração</a>
                                </div>
                            </div>
                        </div>
                        <!-- Fim infoBox -->
                    </div>

                
                
                </div>
        </section>
    </main>
    <?php $render('gerenciador.partials.footer');?>
</div>