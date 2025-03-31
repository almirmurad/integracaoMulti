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
            <div class="content column">               
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
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Entidade</th>
                            <th>Ação</th>
                            <th>Url de Retorno</th>
                            <th>Criado por</th>
                            <th>Data Criação</th>
                            <th>Ativo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($integracoes as $data):?>
                            <tr>
                            <td><?=$data['Id'];?></td>
                            <td><?=$data['EntityId'];?></td>
                            <td><?=$data['ActionId'];?></td>
                            <td><?=$data['CallbackUrl'];?></td>
                            <td><?=$data['CreatorId'];?></td>
                            <td><?=$data['CreateDate'];?></td>
                            <td><?=$data['Active'];?></td>
                            <td style="display: flex;">
                                <a class='btn btn-danger btn-small' href="<?=$base;?>/delHook/<?=$data['Id'];?>">Excluir</a>
                                <!-- <a class='btn btn-success btn-small' href="<?=$base;?>/editHook/<?=$data['Id'];?>">Editar</a> -->
                            </td>
                            </tr>
                        <?php endforeach?>
                    </tbody>
                </table>
                
            </div>
        </section>
    </main>
    <?php $render('gerenciador.partials.footer');?>
</div>