<header>
        <div class="area-logo">
            <a href="<?=$base?>"><img src="<?=$base;?>/assets/img/logoBicorp.png" alt="Logotipo"></a>
        </div>
        <div class="area-user">
            <div class="img-user" onmouseover="show()">
                <img src="<?=$base;?>/assets/uploads/images/users/<?=$loggedUser->id?>/avatars/<?=$loggedUser->avatar?>" alt="avatar"> 
            </div>
            <div class="user-notification"  onmouseover="show()" onmouseout="hiden()" >
            <div class="img-user" id="userImg" onmouseover="show()" onmouseout="hiden()" >
                <img src="<?=$base;?>/assets/uploads/images/users/<?=$loggedUser->id?>/avatars/<?=$loggedUser->avatar?>" alt="avatar" onmouseout="hiden()"> 
            </div>
            <span class="user-name" onmouseover="show()" onmouseout="hiden()"><?=$loggedUser->name; ?></span>
            <span class="tipo-usuario" onmouseover="show()" onmouseout="hiden()"><?=$loggedUser->level; ?></span>
            <a class="logout" href="<?=$base?>/logout" onmouseover="show()" onmouseout="hiden()">Sair</a>
            
            </div>
            <div class="img-notifyer">
            <i class="material-icons notification">notifications</i>
       
            </div>
            <div class="img-help">
                <span style="font-weight: bold;"> ? </span>
            </div>
            
        </div>
    </header>