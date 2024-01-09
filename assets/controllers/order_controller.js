import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [ "quantity", "tickets", "productTotal", "orderTotal" ];

    connect() {
        this.count = 1;
        this.defaultquantity = parseInt(this.ticketsTarget.innerText);
        this.defaulproducttTotal = parseFloat(this.productTotalTarget.innerText).toFixed(2);
        this.defaulOrdertTotal = parseFloat(this.orderTotalTarget.innerText).toFixed(2);
        this.formQuantity = document.getElementById('formQuantity');
    }

    decrement() {
        if (this.count > 1) {
            this.count--;
            this.updateQuantities();
            this.updateTotal();
        }
        
    }

    increment() {
        this.count++;
        this.updateQuantities();
        this.updateTotal();
    }

    updateQuantities() {
        this.quantityTarget.innerText = this.count;
        this.ticketsTarget.innerText = this.defaultquantity * this.count;
        this.formQuantity.value = this.count;
    }

    updateTotal() {
        this.productTotalTarget.innerText = (this.defaulproducttTotal * this.count).toFixed(2);
        this.orderTotalTarget.innerText = (this.defaulOrdertTotal * this.count).toFixed(2);
    }
}
