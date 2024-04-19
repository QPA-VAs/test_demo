@extends('layout')

@section('title')
    <?= get_label('create_client', 'Create client') ?>
@endsection
<style>
    #clientCreateForm .step {
        display: none;
    }

    #clientCreateForm .form-header {
        gap: 5px;
        text-align: center;
        font-size: .9em;
    }

    #clientCreateForm .form-header .stepIndicator {
        position: relative;
        flex: 1;
        padding-bottom: 30px;
    }

    #clientCreateForm .form-header .stepIndicator.active {
        font-weight: 600;
    }

    #clientCreateForm .form-header .stepIndicator.finish {
        font-weight: 600;
        color: #009688;
    }

    #clientCreateForm .form-header .stepIndicator::before {
        content: "";
        position: absolute;
        left: 50%;
        bottom: 0;
        transform: translateX(-50%);
        z-index: 9;
        width: 20px;
        height: 20px;
        background-color: #d5efed;
        border-radius: 50%;
        border: 3px solid #ecf5f4;
    }

    #clientCreateForm .form-header .stepIndicator.active::before {
        background-color: #a7ede8;
        border: 3px solid #d5f9f6;
    }

    #clientCreateForm .form-header .stepIndicator.finish::before {
        background-color: #009688;
        border: 3px solid #b7e1dd;
    }

    #clientCreateForm .form-header .stepIndicator::after {
        content: "";
        position: absolute;
        left: 50%;
        bottom: 8px;
        width: 100%;
        height: 3px;
        background-color: #f3f3f3;
    }

    #clientCreateForm .form-header .stepIndicator.active::after {
        background-color: #a7ede8;
    }

    #clientCreateForm .form-header .stepIndicator.finish::after {
        background-color: #009688;
    }

    #clientCreateForm .form-header .stepIndicator:last-child:after {
        display: none;
    }
</style>
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between m-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ url('/clients') }}"><?= get_label('clients', 'Clients') ?></a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= get_label('create', 'Create') ?>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>


        <div class="card mt-4">
            <div class="card-body">
                <form id="clientCreateForm" action="{{ url('/clients/store') }}" method="POST" class="form-submit-event"
                    enctype="multipart/form-data">
                    <input type="hidden" name="redirect_url" value="/clients">
                    @csrf
                    <!-- start step indicators -->
                    <div class="form-header d-flex mb-4">
                        <span class="stepIndicator">Account Setup</span>
                        <span class="stepIndicator">Business Information</span>
                    </div>
                    <!-- end step indicators -->
                    {{--                Business step --}}
                    <div class="row step">
                        <div class="mb-3 col-md-6">
                            <label for="firstName" class="form-label"><?= get_label('first_name', 'First name') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="first_name" name="first_name"
                                placeholder="<?= get_label('please_enter_first_name', 'Please enter first name') ?>"
                                value="{{ old('first_name') }}">

                            @error('first_name')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror

                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="lastName" class="form-label"><?= get_label('last_name', 'Last name') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="last_name" id="last_name"
                                placeholder="<?= get_label('please_enter_last_name', 'Please enter last name') ?>"
                                value="{{ old('last_name') }}">

                            @error('last_name')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror

                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="email" class="form-label"><?= get_label('email', 'E-mail') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="email" name="email"
                                placeholder="<?= get_label('please_enter_email', 'Please enter email') ?>"
                                value="{{ old('email') }}">

                            @error('email')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>

                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="phone"><?= get_label('phone_number', 'Phone number') ?> <span
                                    class="asterisk">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                placeholder="<?= get_label('please_enter_phone_number', 'Please enter phone number') ?>"
                                value="{{ old('phone') }}">
                            @error('phone')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="dob" class="form-label"><?= get_label('date_of_birth', 'Date of birth') ?>
                                <span class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="dob" name="dob"
                                placeholder="<?= get_label('please_select', 'Please select') ?>" autocomplete="off">

                            @error('dob')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="doj" class="form-label"><?= get_label('date_of_join', 'Date of joining') ?>
                                <span class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="doj" name="doj"
                                placeholder="<?= get_label('please_select', 'Please select') ?>" autocomplete="off">

                            @error('doj')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>


                        <div class="mb-3 col-md-6">
                            <label for="company" class="form-label"><?= get_label('company', 'Company') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="company" name="company"
                                placeholder="<?= get_label('please_enter_company_name', 'Please enter company name') ?>"
                                value="{{ old('company') }}">

                            @error('company')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="address" class="form-label"><?= get_label('address', 'Address') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="address" name="address"
                                placeholder="<?= get_label('please_enter_address', 'Please enter address') ?>"
                                value="{{ old('address') }}">

                            @error('address')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="city" class="form-label"><?= get_label('city', 'City') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="city" name="city"
                                placeholder="<?= get_label('please_enter_city', 'Please enter city') ?>"
                                value="{{ old('city') }}">

                            @error('city')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="state" class="form-label"><?= get_label('state', 'State') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="state" name="state"
                                placeholder="<?= get_label('please_enter_state', 'Please enter state') ?>"
                                value="{{ old('state') }}">

                            @error('state')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="country" class="form-label"><?= get_label('country', 'Country') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="country" name="country"
                                placeholder="<?= get_label('please_enter_country', 'Please enter country') ?>"
                                value="{{ old('country') }}">

                            @error('country')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="zip" class="form-label"><?= get_label('zip_code', 'Zip code') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" id="zip" name="zip"
                                placeholder="<?= get_label('please_enter_zip_code', 'Please enter ZIP code') ?>"
                                value="{{ old('zip') }}">

                            @error('zip')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror


                        </div>


                        @if (isAdminOrHasAllDataAccess())
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for=""><?= get_label('status', 'Status') ?> (<small
                                        class="text-muted mt-2">If the Active option is selected, email verification won't
                                        be required.</small>)</label>
                                <div class="">
                                    <div class="btn-group btn-group d-flex justify-content-center" role="group"
                                        aria-label="Basic radio toggle button group">

                                        <input type="radio" class="btn-check" id="client_active" name="status"
                                            value="1">
                                        <label class="btn btn-outline-primary"
                                            for="client_active"><?= get_label('active', 'Active') ?></label>

                                        <input type="radio" class="btn-check" id="client_deactive" name="status"
                                            value="0" checked>
                                        <label class="btn btn-outline-primary"
                                            for="client_deactive"><?= get_label('deactive', 'Deactive') ?></label>

                                    </div>
                                </div>
                            </div>
                        @endif

                        {{--                    <div class="mt-2"> --}}
                        {{--                        <button type="submit" class="btn btn-primary me-2" id="submit_btn"><?= get_label('create', 'Create') ?></button> --}}
                        {{--                        <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button> --}}
                        {{--                    </div> --}}

                    </div>
                    <div class="row step">
                        <div class="mb-3 col-md-6">
                            <label for="business_name"
                                class="form-label"><?= get_label('business_name', 'Business name') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="business_name" id="business_name"
                                placeholder="<?= get_label('business_name', 'Business name') ?>"
                                value="{{ old('business_name') }}">
                            @error('business_name')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="business_address"
                                class="form-label"><?= get_label('address_line_1', 'Address Line 1') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="business_address" id="business_address"
                                placeholder="<?= get_label('address_line_1', 'Address Line 1') ?>"
                                value="{{ old('business_address') }}">
                            @error('address_line_1')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="address_line_2"
                                class="form-label"><?= get_label('address_line_2', 'Address Line 2') ?></label>
                            <input class="form-control" type="text" name="address_line_2" id="address_line_2"
                                placeholder="<?= get_label('address_line_2', 'Address Line 2') ?>"
                                value="{{ old('address_line_2') }}">
                            @error('address_line_2')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="state_province_region"
                                class="form-label"><?= get_label('state_province_region', 'State / Province / Region') ?>
                                <span class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="state_province_region"
                                id="state_province_region"
                                placeholder="<?= get_label('state_province_region', 'State / Province / Region') ?>"
                                value="{{ old('state_province_region') }}">
                            @error('state_province_region')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="postal_zip_code"
                                class="form-label"><?= get_label('postal_zip_code', 'Postal / Zip Code') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="postal_zip_code" id="postal_zip_code"
                                placeholder="<?= get_label('postal_zip_code', 'Postal / Zip Code') ?>"
                                value="{{ old('postal_zip_code') }}">
                            @error('postal_zip_code')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="website" class="form-label"><?= get_label('website', 'Website') ?></label>
                            <input class="form-control" type="text" name="website" id="website"
                                placeholder="<?= get_label('website', 'Website') ?>" value="{{ old('website') }}">
                            @error('website')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="phone" class="form-label"><?= get_label('phone', 'Phone') ?> <span
                                    class="asterisk">*</span></label>
                            <input class="form-control" type="text" name="phone" id="phone"
                                placeholder="<?= get_label('phone', 'Phone') ?>" value="{{ old('phone') }}">
                            @error('phone')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="prefer_company"
                                class="form-label"><?= get_label('prefer_address_billed', 'Do you prefer to be addressed and billed as your company or personal?*(required)') ?></label>
                            <select class="form-select" name="prefer_company" id="prefer_company">
                                <option value="company" <?php echo old('prefer_company') === 'company' ? 'selected' : ''; ?>><?= get_label('company', 'Company') ?></option>
                                <option value="personal" <?php echo old('prefer_company') === 'personal' ? 'selected' : ''; ?>><?= get_label('personal', 'Personal') ?>
                                </option>
                            </select>
                            @error('prefer_company')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="preferred_correspondence_email"
                                class="form-label"><?= get_label('preferred_correspondence_email', 'Preferred Correspondence Email*(required)') ?></label>
                            <select class="form-select" name="preferred_correspondence_email"
                                id="preferred_correspondence_email">
                                <option value="company" <?php echo old('preferred_correspondence_email') === 'company' ? 'selected' : ''; ?>><?= get_label('company', 'Company') ?></option>
                                <option value="personal" <?php echo old('preferred_correspondence_email') === 'personal' ? 'selected' : ''; ?>><?= get_label('personal', 'Personal') ?>
                                </option>
                            </select>
                            @error('preferred_correspondence_email')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>


                        <div class="mb-3 col-md-6">
                            <label for="preferred_contact_method"
                                class="form-label"><?= get_label('preferred_contact_method', 'Preferred method of contact') ?></label>
                            <input class="form-control" type="text" name="preferred_contact_method"
                                id="preferred_contact_method"
                                placeholder="<?= get_label('preferred_contact_method', 'Preferred method of contact') ?>"
                                value="{{ old('preferred_contact_method') }}">
                            @error('preferred_contact_method')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="applications_used"
                                class="form-label"><?= get_label('applications_used', 'List the applications you use to run your business or workload. I.e. Canva / Slack') ?></label>
                            <input class="form-control" type="text" name="applications_used" id="applications_used"
                                placeholder="<?= get_label('applications_used', 'List the applications you use to run your business or workload. I.e. Canva / Slack') ?>"
                                value="{{ old('applications_used') }}">
                            @error('applications_used')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="maximum_budget"
                                class="form-label"><?= get_label('maximum_budget', 'Do you have a maximum budget per month for VA services?') ?></label>
                            <input class="form-control" type="text" name="maximum_budget" id="maximum_budget"
                                placeholder="<?= get_label('maximum_budget', 'Do you have a maximum budget per month for VA services?') ?>"
                                value="{{ old('maximum_budget') }}">
                            @error('maximum_budget')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label
                                class="form-label"><?= get_label('read_and_understood_terms', 'I can confirm that I have read and understood the QPA Virtual Assistants terms of business.') ?>
                                <span class="asterisk">*</span></label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="agree_terms" id="agree_terms_yes"
                                    value="Yes" <?php echo old('agree_terms') === 'Yes' ? 'checked' : ''; ?>>
                                <label class="form-check-label"
                                    for="agree_terms_yes"><?= get_label('agree_terms', 'Yes') ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="agree_terms" id="agree_terms_no"
                                    value="No" <?php echo old('agree_terms') === 'No' ? 'checked' : ''; ?>>
                                <label class="form-check-label"
                                    for="agree_terms_no"><?= get_label('disagree_terms', 'No') ?></label>
                            </div>
                            @error('agree_terms')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-3 col-md-6">
                            <label
                                class="form-label"><?= get_label('remain_up_to_date_terms', 'I understand that it is my duty to remain up to date with the QPA Virtual Assistants terms of business which will be cascaded via email where there are changes. I understand I will always be given the option to discontinue my services prior to any changes.') ?>
                                <span class="asterisk">*</span></label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="agree_update_terms"
                                    id="agree_update_terms_yes" value="Yes" <?php echo old('agree_update_terms') === 'Yes' ? 'checked' : ''; ?>>
                                <label class="form-check-label"
                                    for="agree_update_terms_yes"><?= get_label('agree_update_terms', 'Yes') ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="agree_update_terms"
                                    id="agree_update_terms_no" value="No" <?php echo old('agree_update_terms') === 'No' ? 'checked' : ''; ?>>
                                <label class="form-check-label"
                                    for="agree_update_terms_no"><?= get_label('disagree_update_terms', 'No') ?></label>
                            </div>
                            @error('agree_update_terms')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>


                        <div class="mb-3 col-md-6">
                            <label for="signature"
                                class="form-label"><?= get_label('insert_signature_name', 'Insert name as form of signature *(required)') ?></label>
                            <input class="form-control" type="text" name="signature" id="signature"
                                placeholder="<?= get_label('insert_signature_name', 'Insert name as form of signature *(required)') ?>"
                                value="{{ old('signature') }}">
                            @error('insert_signature_name')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>


                    <!-- start previous / next buttons -->
                    <div class="form-footer d-flex">
                        <button class="btn btn-primary me-2 prevBtn" type="button" id="prevBtn"
                            onclick="nextPrev(-1)">Previous</button>
                        <button class="btn btn-outline-secondary nextBtn ms-2" type="button" id="nextBtn"
                            onclick="nextPrev(1)">Next</button>
                        <div class="ms-2" id="submit_cancel">
                            <button type="submit" class="btn btn-primary me-2"
                                id="submit_btn"><?= get_label('create', 'Create') ?></button>
                            <button type="reset"
                                class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                        </div>
                    </div>
                    <!-- end previous / next buttons -->
                </form>
            </div>
        </div>
    </div>
    <script>
        let currentTab = 0; // Current tab is set to be the first tab (0)
        showTab(currentTab); // Display the current tab

        function showTab(n) {
            const tabs = document.getElementsByClassName("step");
            const prevBtn = document.getElementById("prevBtn");
            const nextBtn = document.getElementById("nextBtn");
            const submitCancelBtn = document.getElementById("submit_cancel");

            for (let i = 0; i < tabs.length; i++) {
                tabs[i].style.display = "none";
            }

            tabs[n].style.display = "flex";

            if (n === 0) {
                prevBtn.style.display = "none";
                submitCancelBtn.style.display = "none";
            } else {
                prevBtn.style.display = "inline";
            }

            if (n === tabs.length - 1) {
                nextBtn.style.display = "none";
                nextBtn.innerHTML = "Submit";
                submitCancelBtn.style.display = "block";
            } else {
                nextBtn.style.display = "block";
                nextBtn.innerHTML = "Next";
                submitCancelBtn.style.display = "none";
            }

            fixStepIndicator(n);
        }


        function nextPrev(n) {
            // This function will figure out which tab to display
            let x = document.getElementsByClassName("step");
            // Exit the function if any field in the current tab is invalid:
            if (n == 1 && !validateForm()) return false;
            // Hide the current tab:
            x[currentTab].style.display = "none";
            // Increase or decrease the current tab by 1:
            currentTab = currentTab + n;
            // if you have reached the end of the form...
            if (currentTab >= x.length) {
                // ... the form gets submitted:
                document.getElementById("clientCreateForm").submit();
                return false;
            }
            // Otherwise, display the correct tab:
            showTab(currentTab);
        }

        function validateForm() {
            // This function deals with validation of the form fields
            let x, y, i, valid = true;
            x = document.getElementsByClassName("step");
            y = x[currentTab].getElementsByTagName("input");
            // A loop that checks every input field in the current tab:
            for (i = 0; i < y.length; i++) {
                // If a field is empty...
                if (y[i].value == "") {
                    // add an "invalid" class to the field:
                    y[i].className += " invalid";
                    // and set the current valid status to false
                    valid = false;
                }
            }
            // If the valid status is true, mark the step as finished and valid:
            if (valid) {
                document.getElementsByClassName("stepIndicator")[currentTab].className += " finish";
            }
            return valid; // return the valid status
        }

        function fixStepIndicator(n) {
            // This function removes the "active" class of all steps...
            let i, x = document.getElementsByClassName("stepIndicator");
            for (i = 0; i < x.length; i++) {
                x[i].className = x[i].className.replace(" active", "");
            }
            //... and adds the "active" class on the current step:
            x[n].className += " active";
        }
    </script>
@endsection
