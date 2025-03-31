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
                <!-- <div class="area-filtro">
                    <span>Integrações:</span>
                    <a class="button" href=""><span>Salvar</span><i class="material-icons btn">more_vert</i></a>
                    
                </div> -->
            </div>
            <div class="content colum " >               
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
                <div class="area-form flex row" style="margin:0 25%;">

                
                <form method="Post" action="<?=$base?>/addPermissionGroupAction">
               
                    <div class="campo <?=(!empty($flash))? 'has-error':''?>">
                        <label class="form-lbl" for="name">Nome:</label>
                        <input class="form-cmps" type="text" name="name" placeholder="Digite o nome do grupo de usuário" id="name">
                    </div>
             
                    <?php foreach($items as $item):?>
                        <div class="campo">
                            <input class="checkBox"type="checkbox" name="itemPermission[]" value="<?=$item['id']?>" id="item-<?=$item['id']?>">
                            <label class="form-lbl" for="item-<?=$item['id']?>"> <?=$item['name']?> </label>
                        </div>
                    <?php endforeach?>

                    <button class="btn btn-submit" type="submit">Salvar</button>

          
                </form>   
                </div>
            </div>
            
        </section>
    </main>
    <?php $render('gerenciador.partials.footer');?>
</div>