(function ($) {
	"use strict";

	const dateMap =
		window.capTrialReg && capTrialReg.venueDateMap
			? capTrialReg.venueDateMap
			: {};
	let checkoutSession =
		window.capTrialReg && capTrialReg.checkoutSession
			? capTrialReg.checkoutSession
			: "";

	function updateProgress(step) {
		const percent = (step / 3) * 100;
		$(".cap-progress-bar-fill").css("width", percent + "%");
	}

	function showStep(stepNo) {
		$(".cap-form-step").hide();
		const $step = $('.cap-form-step[data-step="' + stepNo + '"]');
		$step.fadeIn(200);

		$(".cap-step-indicator")
			.removeClass("active completed")
			.each(function () {
				const s = parseInt($(this).data("step"), 10);
				if (s < stepNo) {
					$(this).addClass("completed");
				}
				if (s === stepNo) {
					$(this).addClass("active");
				}
			});

		updateProgress(stepNo);
		$("html, body").animate(
			{
				scrollTop: $(".cap-reg-form").offset().top - 40,
			},
			300,
		);
	}

	function refreshTrialDates() {
		const venue = $("#cap_preferred_trial_venue").val();
		const $date = $("#cap_preferred_trial_date");

		$date.empty().append('<option value="">Select Date</option>');
		if (venue && dateMap[venue]) {
			dateMap[venue].forEach(function (date) {
				$date.append(`<option value="${date}">${date}</option>`);
			});
		}
	}

	$(document).on("change", "#cap_preferred_trial_venue", refreshTrialDates);
	refreshTrialDates();

	function clearFieldErrors(scope) {
		(scope || $(".cap-reg-form"))
			.find(".cap-field-error")
			.removeClass("cap-field-error")
			.end()
			.find(".cap-input-error-msg")
			.remove();
	}

	function renderErrors(errors) {
		const $box = $(".cap-reg-errors");
		if (!errors || !errors.length) {
			$box.hide().empty();
			return;
		}
		$box.html(errors.map((error) => `<p>${error}</p>`).join("")).show();
	}

	function getFieldLabel($field) {
		const id = $field.attr("id");
		if (id) {
			const labelText = $(`label[for="${id}"]`).first().text().trim();
			if (labelText) {
				return labelText;
			}
		}
		return "This field";
	}

	function showFieldError($field, message) {
		const $wrap = $field.closest(".cap-field").length
			? $field.closest(".cap-field")
			: $field.closest("label");
		$field.addClass("cap-field-error");

		if ($wrap.find(".cap-input-error-msg").length === 0) {
			$wrap.append(`<div class="cap-input-error-msg">${message}</div>`);
		}
	}

	function validateStep(stepNo, renderInlineErrors = false) {
		let valid = true;
		const $step = $(`.cap-form-step[data-step="${stepNo}"]`);

		if (renderInlineErrors) {
			clearFieldErrors($step);
		}

		$step.find("[required]").each(function () {
			const $field = $(this);

			if ($field.is(":checkbox")) {
				if (!$field.is(":checked")) {
					valid = false;
					if (renderInlineErrors) {
						showFieldError($field, "This checkbox is required");
					}
				}
				return;
			}

			if (!$field.val()) {
				valid = false;
				if (renderInlineErrors) {
					showFieldError($field, `${getFieldLabel($field)} is required`);
				}
			}
		});

		return valid;
	}

	function calculateAgeYears(dob) {
		const d = new Date(dob);
		const t = new Date();
		let age = t.getFullYear() - d.getFullYear();
		const m = t.getMonth() - d.getMonth();
		if (m < 0 || (m === 0 && t.getDate() < d.getDate())) {
			age--;
		}
		return age;
	}

	$(document).on("click", ".cap-step-next", function () {
		const step = parseInt($(this).data("current-step"), 10);

		if (!validateStep(step, true)) {
			return;
		}

		if (step === 1) {
			const age = calculateAgeYears($("#date_of_birth").val());
			if (age < 10 || age > 21) {
				showFieldError($("#date_of_birth"), "Age must be 10–21");
				return;
			}
		}

		showStep(step + 1);
	});

	$(document).on("click", ".cap-step-prev", function () {
		const step = parseInt($(this).data("current-step"), 10);
		showStep(step - 1);
	});

	showStep(1);

	$(document).on("submit", ".cap-reg-form", function (e) {
		e.preventDefault();

		if (!validateStep(1, true)) {
			return showStep(1);
		}
		if (!validateStep(2, true)) {
			return showStep(2);
		}
		if (!validateStep(3, true)) {
			return showStep(3);
		}

		const age = calculateAgeYears($("#date_of_birth").val());
		if (age < 10 || age > 21) {
			showStep(1);
			showFieldError($("#date_of_birth"), "Age must be 10–21");
			return;
		}

		const $btn = $(this).find('[type="submit"]');
		$btn.prop("disabled", true).addClass("loading").text("Processing...");

		const payload = $(this).serializeArray();
		payload.push({ name: "action", value: "cap_prepare_checkout" });
		payload.push({ name: "nonce", value: capTrialReg.prepareCheckoutNonce });

		$.post(capTrialReg.ajaxUrl, payload)
			.done(function (res) {
				if (!res.success) {
					renderErrors(res.data && res.data.errors ? res.data.errors : ["Validation failed"]);
					$btn
						.prop("disabled", false)
						.removeClass("loading")
						.text("Proceed to Payment");
					return;
				}

				checkoutSession = res.data.checkout_session;
				$("#cap-checkout-form-panel").hide();
				$("#cap-checkout-payment-panel").fadeIn();

				$("html, body").animate(
					{
						scrollTop: $("#cap-checkout-payment-panel").offset().top - 40,
					},
					300,
				);
			})
			.fail(function () {
				renderErrors(["Server error. Try again."]);
				$btn
					.prop("disabled", false)
					.removeClass("loading")
					.text("Proceed to Payment");
			});
	});

	$(document).on("input change", "input, select, textarea", function () {
		$(this)
			.removeClass("cap-field-error")
			.closest(".cap-field")
			.find(".cap-input-error-msg")
			.remove();
	});

	$(document).on("click", "#cap-pay-now", function () {
		if (!checkoutSession) {
			return alert("Submit form first");
		}

		const $result = $("#cap-payment-result").text("Preparing payment...");
		$.post(capTrialReg.ajaxUrl, {
			action: "cap_create_razorpay_order",
			nonce: capTrialReg.createOrderNonce,
			checkout_session: checkoutSession,
		}).done(function (res) {
			if (!res.success) {
				$result.text("Order failed");
				return;
			}

			const rzp = new Razorpay({
				key: res.data.key,
				amount: res.data.amount,
				currency: res.data.currency,
				order_id: res.data.order_id,
				name: "CAP Scholarship",
				handler: function (response) {
					$.post(capTrialReg.ajaxUrl, {
						action: "cap_verify_payment",
						nonce: capTrialReg.verifyPaymentNonce,
						checkout_session: checkoutSession,
						thank_you_url: capTrialReg.thankYouUrl || "",
						...response,
					}).done(function (verify) {
						if (verify.success && verify.data.redirect_url) {
							window.location.href = verify.data.redirect_url;
						}
					});
				},
				theme: { color: "#0f62fe" },
			});

			rzp.open();
			$result.text("");
		});
	});
})(jQuery);
