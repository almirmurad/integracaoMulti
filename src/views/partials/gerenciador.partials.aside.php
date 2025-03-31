<aside>
    <nav>
        <h4>Operações</h4>
        <ul>
            <li><a  href="<?=$base?>/">Dashboard</a></li>
            <li><a  href="<?=$base?>/interactions">Iterações</a></li>
            <li><a  href="<?=$base?>/deals">Deals</a></li>
            
            <?php if(in_array('register_integration', $loggedUser->permission)):?>
            <li><a  href="<?=$base?>/integrar">Integrar Novo +</a></li>
            <?php endif?>
            <?php if(in_array('integrations_view', $loggedUser->permission)):?>
            <li><a  href="<?=$base?>/getAll">Buscar Todas</a></li>
            <?php endif?>
            <?php if(in_array('configurations_view', $loggedUser->permission)):?>
            <li><a  href="<?=$base?>/configs">Configurações</a></li>
            <?php endif?>
            <?php if(in_array('users_view', $loggedUser->permission)):?>
            <li><a  href="<?=$base?>/users">Usuários</a></li>
            <?php endif?>
            <?php if(in_array('permissions_view', $loggedUser->permission)):?>
            <li><a  href="<?=$base?>/permissions">Permissões</a></li>
            <?php endif?>





            <!--<li><a class="<?=($activeMenu == 'pendentes')?'active':''?>" href="<?=$base?>/pendentes">Pendentes</a></li>
            <li><a class="<?=($activeMenu == 'inativos')?'active':''?>" href="<?=$base?>/inativos">Inativos</a></li>
            <li><a class="<?=($activeMenu == 'relatorios')?'active':''?>" href="">Tarefas</a></li>
            <li><a class="<?=($activeMenu == 'tarefas')?'active':''?>" href="">Relatórios</a></li> -->
        </ul>
    </nav>
</aside>