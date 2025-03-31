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
            <div class="content center-center">
                <p>
                    Cria um novo Webhook no Ploomes a partir de uma Entidade e uma Ação da Entidade referida. <br>
                    o Url de retorno é o url que receberá os dados vindos do Webhook.
                </p>
                <div class="area-form">
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
                    <form method="post" action="<?=$base?>/integrar">
                        <div class="campo">
                            <label class="form-lbl" for="entityId">Entidade</label>
                            <select class="form-cmps" name="entityId" id="entityId">
                                <option value="1">Cliente</option>
                                <option value="2">Negócio</option>
                                <option value="10">Produtos</option>
                                <option value="4">Vendas</option>
                            </select>
                        </div>
                        <div class="campo">
                        <label class="form-lbl" for="actionId">Ação</label>
                            <select class="form-cmps" name="actionId" id="actionId">
                                <option value="1">Criar</option>
                                <option value="2">Atualizar</option>
                                <option value="3">Excluir</option>
                                <option value="4">Ganhar</option>
                                <option value="5">Perder</option>
                                <option value="6">Reabrir</option>
                            </select>
                        </div>
                        <div class="campo">
                        <label class="form-lbl" for="cbUrl">URL de retorno</label>
                            <input class="form-cmps" type="url" name="cbUrl" id="cbUrl">
                        </div>
                        <button type="submit" class="btn-submit">Enviar</button>
                    </form>
                </div>
                
            </div>
        </section>
    </main>
    <?php $render('gerenciador.partials.footer');?>
</div>