function blankresponse(element) { 
   
    // Get the form element (question)
    el = document.getElementsByName(element);
  
    // Uncheck the group
    for (var i=0;i<el.length;i++) {
        if(el[i].type == 'radio') {                     
            el[i].checked=false;                        
        }
    }      
}

