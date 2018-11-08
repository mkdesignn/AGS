function currency(value){

    if( value !== "" && value !== undefined){

        var new_price = value.toString().replace(/,/gi, '');
        var split = new_price.toString().split("");

        var convert_new_price = convertPrice(split[split.length - 1]),
            converted_before = "";
        if( new_price.length > 1 )
            converted_before = new_price.substr(0, new_price.length - 1) + convert_new_price;
        else
            converted_before = convert_new_price;

        new_price = converted_before.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return new_price;
    }
}

function reverseCurrency(value){
    if( value !== undefined )
        return parseInt(value.toString().replace(/,/gi, ''));
}

function convertPrice(value){

    //۱۲۳۴۵۶۷۸۹۰
    if( value !== undefined ){
        var num = '';
        switch(value){
            case '۱':
                num = 1;
                break;
            case '۲':
                num = 2;
                break;
            case '۳':
                num = 3;
                break;
            case '۴':
                num = 4;
                break;
            case '۵':
                num = 5;
                break;
            case '۶':
                num = 6;
                break;
            case '۷':
                num = 7;
                break;
            case '۸':
                num = 8;
                break;
            case '۹':
                num = 9;
                break;
        }

        if( value == '۰' )
            return "0";

        return ( num == '' ) ? value.toString() : num.toString();
    }

}


function justPersian(str){
    var p = /^[\u0600-\u06FF\s]+$/;

    if(str != 'Backspace' && str != 'Tab')
        if (!p.test(str))
            return false;

    return true;
}

export {
    currency, reverseCurrency, justPersian
}