/*Define class*/
class ezPopup{
    //This is the name of the class that will be assigned to popups. Use it to control the popup visual via CSS.
    constructor(className) {
        this.className = className;
        this.offsetX = 15;
        this.offsetY = 5;
        this.elemPopupPairs = {};
    }
    /*Use this to control offsets - expecting a 2 length array of the form [<x offset>, <y offset>]*/
    setOffset(inputArray){
        this.offsetX = inputArray[0];
        this.offsetY = inputArray[1];
    }

    /*Use this to get a 2 length array of the form [<x offset>, <y offset>]*/
    getOffset(inputArray){
        return [this.offsetX, this.offsetY];
    }

    /*Use this to initiate a specific element, given its id.
    * You might specify the id of the created popup - if you do not, it will be chosen randomly (no duplicates).
    * */
    initPopup(elemID, popupText,popupID = ''){
        // create tooltip element.
        const ttBox = document.createElement("div");
        let targetID = Math.floor(Math.random()*10000)+1;
        if(popupID != '')
            targetID = popupID;
        else{
            if(this.elemPopupPairs[elemID]!== undefined){
                targetID = this.elemPopupPairs[elemID];
            }
            else{
                while(document.getElementById('tooltip-'+targetID) != null){
                    targetID  = Math.floor(Math.random()*10000)+1;
                }
                targetID = 'tooltip-'+targetID;
                this.elemPopupPairs[elemID] = targetID;
            }
        }
        // set style
        let oldElement = document.getElementById(targetID);
        if(oldElement != null)
            oldElement.parentNode.removeChild(oldElement);
        ttBox.id = targetID;
        ttBox.style.visibility = "hidden"; // make it hidden till mouse over
        ttBox.style.position = "fixed"; // make it hidden till mouse over
        ttBox.className = this.className;

        // insert into DOM
        document.body.appendChild(ttBox);

        const ttTurnOn = ((evt) => {
            // get the position of the hover element
            const boundBox = evt.target.getBoundingClientRect();
            const coordX = boundBox.left;
            const coordY = boundBox.top;

            // adjust bubble position
            ttBox.style.left = (coordX + this.offsetX).toString() + "px";
            ttBox.style.top = (coordY + this.offsetY).toString() + "px";

            // add bubble content. Can include image or link
            ttBox.innerHTML = popupText;
            // make bubble VISIBLE
            ttBox.style.visibility = "visible";
        });

        const ttTurnOff = ((evt) => { ttBox.style.visibility = "hidden"; });

        const hoverEle = document .getElementById(elemID);
        // assign handler
        hoverEle.addEventListener("mouseover", ttTurnOn , false);
        hoverEle.addEventListener("mouseout", ttTurnOff , false);
        document.getElementById(elemID).addEventListener("click", ttTurnOff , false);
    }

};