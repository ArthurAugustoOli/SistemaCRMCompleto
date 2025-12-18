// Main JavaScript file for StyleHub e-commerce

document.addEventListener("DOMContentLoaded", () => {
  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })

  // Product image gallery (for product detail page)
  const productThumbnails = document.querySelectorAll(".product-thumbnail")
  if (productThumbnails.length > 0) {
    productThumbnails.forEach((thumbnail) => {
      thumbnail.addEventListener("click", function () {
        const mainImage = document.getElementById("main-product-image")
        if (mainImage) {
          mainImage.src = this.src

          // Remove active class from all thumbnails
          productThumbnails.forEach((thumb) => {
            thumb.classList.remove("border-dark")
          })

          // Add active class to clicked thumbnail
          this.classList.add("border-dark")
        }
      })
    })
  }

  // Form validation for checkout
  const forms = document.querySelectorAll(".needs-validation")
  if (forms.length > 0) {
    Array.from(forms).forEach((form) => {
      form.addEventListener(
        "submit",
        (event) => {
          if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
          }

          form.classList.add("was-validated")
        },
        false,
      )
    })
  }

  // Payment method toggle
  const paymentMethodRadios = document.querySelectorAll('input[name="paymentMethod"]')
  const creditCardDetails = document.getElementById("credit-card-details")

  if (paymentMethodRadios.length > 0 && creditCardDetails) {
    paymentMethodRadios.forEach((radio) => {
      radio.addEventListener("change", function () {
        if (this.value === "credit") {
          creditCardDetails.style.display = "block"
        } else {
          creditCardDetails.style.display = "none"
        }
      })
    })
  }
})
