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
                    <a class="button" href="<?=$base?>/addPermissionGroup"><span>Adicionar</span><i class="material-icons btn">more_vert</i></a>
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
                            <th>Nome</th>
                            <th>Total Usuarios Ativos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach($list as $data):?>
                            <tr>
                            <td><?=$data['namePermission'];?></td>
                            <td><?=$data['totalUserPermission'];?></td>
                            <td style="display: flex; justify-content:center;">
                                <a class='btn btn-danger btn-small' href="<?=$base;?>/delGroupPermission/<?=$data['idPermission'];?>" <?=($data['totalUserPermission'] > 0 )? 'disabled':''?>>Excluir</a>
                                <a class='btn btn-success btn-small' href="<?=$base;?>/editPermissionGroup/<?=$data['idPermission'];?>">Editar</a>
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