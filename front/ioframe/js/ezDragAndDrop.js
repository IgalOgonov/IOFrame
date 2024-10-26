
//Use this to disable all child pointer events except those who have the attribute specified by exclude
function disableChildPointerEvents(targetObjID, exclude = 'draggable-element') {
    let targetObj = document.querySelector('#'+targetObjID);
    let cList = targetObj.childNodes;
    for (let i = 0; i < cList.length; ++i) {
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