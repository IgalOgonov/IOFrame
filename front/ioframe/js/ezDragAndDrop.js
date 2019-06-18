
//Use this to disable all child pointer events except those who have the attribute specified by exclude
function disableChildPointerEvents(targetObjID, exclude = 'draggable-element') {
    targetObj = document.querySelector('#'+targetObjID);
    var cList = targetObj.childNodes;
    for (i = 0; i < cList.length; ++i) {
        try{
            if(!cList[i].hasAttribute(exclude))
                cList[i].style.pointerEvents = 'none';
            if (cList[i].hasChildNodes())
                disableChildPointerEvents(cList[i])
        } catch (err) {
            //
        }
    }
}