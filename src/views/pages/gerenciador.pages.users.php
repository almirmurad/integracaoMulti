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
                    <span>Usuários:</span>
                    <a class="button" href="<?=$base?>/addUser"><span>Adiciona+</span></a>
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
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Avatar</th>
                            <th>Tipo</th>
                            <th>Permissão</th>
                            <th>Ativo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach($users as $data):?>
                            <tr>
                            <td><?=$data->id;?></td>
                            <td><?=$data->name;?></td>
                            <td><?=$data->mail;?></td>
                            <td><?=$data->avatar;?></td>
                            <td><?=$data->type?></td>
                            <td><?=$data->id_permission;?></td>
                            <td><?=$data->active;?></td>
                            <td style="display: flex;">
                                <a class='btn btn-danger btn-small' href="<?=$base;?>/delUser/<?=$data->id;?>">Excluir</a>
                                <a class='btn btn-success btn-small' href="<?=$base;?>/user/<?=$data->id;?>/editUser">Editar</a>
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