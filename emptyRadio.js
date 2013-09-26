function blankresponse(element) { 
   
   var escondido = element+'_hiden';   
   el = document.getElementsByName(element);
  
    for (var i=0;i<el.length;i++) {
   		if(el[i].type == 'radio') {                     
			el[i].checked=false;                        
		}
	}       
     
     document.getElementById(escondido).checked = true;      
      
}

