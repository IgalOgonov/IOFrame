<?php
echo '
    <style>
        body > .wrapper{
            flex-direction: column;
        }
        #login-register.main-app,
        #account.main-app{
            margin: auto;
        }
        #banned{
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        #banned .link-container{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        #banned .link-container > *{
            margin: 2px 10px;
        }
    </style>
    <div id="banned" style="">
      <h2>User Banned</h2>
      <h3>Until '.date('H:i, d M Y eP',$banned).'</h3>
      <div class="link-container">
          <a href="../cp/login">Login Page</a>
          <a href="../cp/account">Account Page</a>
      </div>
    </div>
    ';