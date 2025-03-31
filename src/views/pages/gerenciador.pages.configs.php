<div class="wrap">
    <?php $render('gerenciador.partials.head'); ?>
    <?php $render('gerenciador.partials.header', ['loggedUser' => $loggedUser]); ?>
    <main>
    <?php $render('gerenciador.partials.aside',['loggedUser'=>$loggedUser]);?>

        <section>
            <div class="title">
                <div class="area-title">
                    <h2>Você está em: <?= $pagina ?></h2>
                </div>
                <div class="area-filtro">
                    <span>Integrações:</span>
                    <a class="button" href=""><span>Filtrar</span><i class="material-icons btn">more_vert</i></a>
                    <!-- <span>Canais:</span>
                <a class="button" href=""><span>Filtrar</span><i class="material-icons btn">more_vert</i></a> -->
                </div>
            </div>
            <div class="content">
                <div class="config-top">
                    <div class="title-congig">
                       <h2>Configurações da Base Ploomes</h2>  
                       <?php if (!empty($flash)) : ?>
                        <div class="area-flash flex center-center">
                            <i class="material-symbols-outlined">
                                warning
                            </i>
                            <h4 class="text-flash">
                                <?= $flash; ?>
                            </h4>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    
                    <div class="content-config">
                    
                        <!-- Início infoBox -->
                        <div class="info-Box">
                            <div class="title">
                                <h2>Ploomes APIKEY</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <div class="area-form-configs">
                                        
                                        <form method="post" action="<?= $base ?>/define">
                                            
                                            <div class="campo">
                                                <label class="form-lbl" for="apiKeyPlm">API_KEY</label>
                                                <input type="text" class="form-cmps" name="apiKeyPlm" id="apiKeyPlm">
                                            </div>
                                            
                                            <button type="submit" class="btn-submit" name="submitPlm">Enviar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        
                        <!-- Fim infoBox -->
                    </div>
                </div>

                <div class="config-bottom">
                    <div class="title-congig">
                       <h2>Configurações das bases do Omie ERP</h2>  
                    </div>
                    <div class="content-config">
                        <!-- Início infoBox -->
                        <div class="info-Box">
                            <div class="title">
                                <h2>Manos-PR</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <div class="area-form-configs">
            
                                        <form method="post" action="<?= $base ?>/define">
                                            
                                            <div class="campo">
                                                <label class="form-lbl" for="secretKeyMpr">SECRET_KEY</label>
                                                <input type="text" class="form-cmps" name="secretKeyMpr" id="secretKeyMpr">
                                                
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="appKeyMpr">APP_KEY</label>
                                                <input type="text" class="form-cmps" name="appKeyMpr" id="appKeyMpr">
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="nccMpr">NCC</label>
                                                <input type="text" class="form-cmps" name="nccMpr" id="nccMpr" placeholder="Número da conta corrente">
                                            </div>
                                            
                                            <button type="submit" class="btn-submit" name="submitMpr">Enviar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="info-Box">
                            <div class="title">
                                <h2>Manos-SC</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <div class="area-form-configs">
                                        
                                        <form method="post" action="<?= $base ?>/define">
                                            
                                        <div class="campo">
                                                <label class="form-lbl" for="secretKeyMsc">SECRET_KEY</label>
                                                <input type="text" class="form-cmps" name="secretKeyMsc" id="secretKeyMsc">
                                                
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="appKeyMsc">APP_KEY</label>
                                                <input type="text" class="form-cmps" name="appKeyMsc" id="appKeyMsc">
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="nccMsc">NCC</label>
                                                <input type="text" class="form-cmps" name="nccMsc" id="nccMsc" placeholder="Número da conta corrente">
                                            </div>
                                            
                                            <button type="submit" class="btn-submit" name="submitMsc">Enviar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        <div class="info-Box">
                            <div class="title">
                                <h2>Manos Homologação</h2>
                            </div>
                            <div class="content-box">
                                <div class="content-info">
                                    <div class="area-form-configs">
                                        
                                        <form method="post" action="<?= $base ?>/define">
                                            
                                            <div class="campo">
                                                <label class="form-lbl" for="secretKeyMhl">SECRET_KEY</label>
                                                <input type="text" class="form-cmps" name="secretKeyMhl" id="secretKeyMhl">
                                                
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="appKeyMhl">APP_KEY</label>
                                                <input type="text" class="form-cmps" name="appKeyMhl" id="appKeyMhl">
                                            </div>
                                            <div class="campo">
                                                <label class="form-lbl" for="nccMhl">NCC</label>
                                                <input type="text" class="form-cmps" name="nccMhl" id="nccMhl" placeholder="Número da conta corrente">
                                            </div>
                                            
                                            <button type="submit" class="btn-submit" name="submitMhl">Enviar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>      
                        <!-- Fim infoBox -->    
                    </div>
                </div>
                

                
            </div>
        </section>
    </main>
    <?php $render('gerenciador.partials.footer'); ?>
</div>