<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="<?=$base;?>/assets/css/style.css" />
    <link rel="stylesheet" type="text/css" href="<?=$base;?>/assets/css/login.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>Bicorp - Omnichannel | Cadastro</title>
</head>
<body>

    <main>
        <aside>
            <div class="logo">
                <img src="<?=$base;?>/assets/img/capa_face.png" alt="Logotipo">
            </div>
        </aside>
        <section>
                <div class="cadastre">
                    <span>Já tem cadastro? <a href="<?=$base?>/login"> Faça Login</a></span>
                </div>
                <div class="content-login">
                    <div class="area-form">
                        <form method="POST" action="<?=$base?>/cadastro">
                            <div class="field">
                                <h2>Cadastre-se</h2>
                            </div>
                            
                                <?php if(!empty($flash)):?>
                                    <div class="flash"> <?php echo $flash; ?> </div>
                                <?php endif; ?>
                            <div class="field">
                            <label class="label"><span>Nome</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" name="name" type="text" placeholder="Digite seu nome" /></p>
                            </div>
                            <div class="field">
                                <label class="label"><span>Email</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" name="email" type="email" placeholder="email@email.com" /></p>
                            </div>
                            <div class="field">
                                <label class="label"><span>Data de Nascimento</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" id="birthdate" name="birthdate" type="text" placeholder="Digite sua data de Nascimento" /></p>
                            </div>
                            <div class="field">
                                <label class="label"><span>Senha</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" name="password" type="password" placeholder="Digite sua senha" /></p>
                            </div>
                            <div class="field">
                                <!-- após arrumar o formulário apagar o link e substituir pelo botão -->
                                 <button class="btn-submit" >Enviar</button> 
                                <!-- <a class="btn-submit" href="home">Enviar</a>-->
                            </div>
                        </form>
                    </div>
                </div>
        </section>
    </main>
 <script src="https://unpkg.com/imask"></script> 
 <script>
     IMask(
        document.getElementById('birthdate'),
        {
            mask:'00/00/0000'
        }
     );
 </script>  
</body>
</html>