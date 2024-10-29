window.onload = function(){
    var popUp = document.querySelector('[data-slug="all-in-one-performance-accelerator"] .deactivate a');
    if (typeof(popUp) != 'undefined' && popUp != null){
        var urlRedirect = popUp.getAttribute('href');
        popUp.onclick = function(event){
            event.preventDefault()
            var removeModal = document.getElementById("myModal");
            if (typeof(removeModal) != 'undefined' && removeModal != null){
            }else{
                popupModal();
            }
            document.getElementById('myModal').style.display='block'
        }
    }  
    function popupModal(){
        var loaderdiv = document.createElement('div');
        loaderdiv.setAttribute('id','loader');
        document.body.appendChild(loaderdiv);

        var maindiv = document.createElement('div');
        maindiv.setAttribute('id','myModal');
        maindiv.setAttribute('class','myModal');
        document.body.appendChild(maindiv);

        var modalContent = document.createElement("div");
        modalContent.setAttribute('class','modal-content');
        maindiv.appendChild(modalContent);

        var header = document.createElement("div")
        header.setAttribute('style','display:flex;align-items:center;justify-content:space-between;margin-top: -16px;')

      

        var headerhead = document.createElement("h3");
        var headerheadS = document.createElement("strong");
        headerheadS.setAttribute('style','color: #016b63;')
        
        headerheadS.innerHTML = "All-in-one Performance Accelerator";

        headerhead.appendChild(headerheadS);
        header.appendChild(headerhead);

        var close = document.createElement("span");
        close.setAttribute('class',"close-button");
        close.setAttribute('onclick',"document.getElementById('myModal').style.display='none'")
        close.innerHTML = "×";
        header.appendChild(close);

        modalContent.appendChild(header);

        var body = document.createElement('div');
        body.setAttribute('class','card cardView');
        modalContent.appendChild(body);

        var bodyhead = document.createElement("h4");
        var bodyheadS = document.createElement("strong");
        bodyheadS.innerHTML = "Enabled drop table option , it will remove all your settings configurations";

        bodyhead.appendChild(bodyheadS);
        body.appendChild(bodyhead);

       

        var footer = document.createElement("div");
        footer.setAttribute('style','float:right');
        modalContent.appendChild(footer);

        var submitBtn = document.createElement('input');
        submitBtn.setAttribute('type','button');
        submitBtn.setAttribute('id','mailsubmit');
        submitBtn.setAttribute('name','mailsubmit');
        submitBtn.setAttribute('class','button button-secondary button-deactivate allow-deactivate');
        submitBtn.setAttribute('value','Submit & Deactivate');
        submitBtn.setAttribute('onclick','submitReason()')
        submitBtn.setAttribute('style','display:none;margin-right:10px;');
        footer.appendChild(submitBtn);

        var skipBtn = document.createElement('a');
        skipBtn.setAttribute('id','skipanddeactivate');
        skipBtn.setAttribute('class','button button-secondary button-deactivate allow-deactivate');
        skipBtn.setAttribute('style','margin-right:10px;background-color: #016b63; color: white;');
       
        skipBtn.setAttribute('href',urlRedirect)
        skipBtn.innerHTML = "OK";
        footer.appendChild(skipBtn);

        var cancelBtn = document.createElement('span');
        cancelBtn.setAttribute('class','button button-secondary button-close');
        cancelBtn.setAttribute('onclick',"document.getElementById('myModal').style.display='none'");
        cancelBtn.setAttribute('style','background-color: #016b63; color: white;');
        cancelBtn.innerHTML = "Cancel";
        footer.appendChild(cancelBtn); 
    }
}