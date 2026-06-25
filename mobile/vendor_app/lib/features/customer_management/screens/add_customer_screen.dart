import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_app_bar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_button_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/custom_snackbar_widget.dart';
import 'package:sixvalley_vendor_app/common/basewidgets/textfeild/custom_text_feild_widget.dart';
import 'package:sixvalley_vendor_app/features/auth/widgets/code_picker_widget.dart';
import 'package:sixvalley_vendor_app/features/customer_management/controllers/customer_controller.dart';
import 'package:sixvalley_vendor_app/features/pos/domain/models/customer_body.dart';
import 'package:sixvalley_vendor_app/features/splash/controllers/splash_controller.dart';
import 'package:sixvalley_vendor_app/helper/email_checker.dart';
import 'package:sixvalley_vendor_app/localization/language_constrants.dart';
import 'package:sixvalley_vendor_app/utill/dimensions.dart';

class AddCustomerScreen extends StatefulWidget {
  const AddCustomerScreen({super.key});

  @override
  State<AddCustomerScreen> createState() => _AddCustomerScreenState();
}

class _AddCustomerScreenState extends State<AddCustomerScreen> {
  final TextEditingController _fName = TextEditingController();
  final TextEditingController _lName = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _country = TextEditingController();
  final TextEditingController _city = TextEditingController();
  final TextEditingController _zipCode = TextEditingController();
  final TextEditingController _address = TextEditingController();

  final FocusNode _fNameNode = FocusNode();
  final FocusNode _lNameNode = FocusNode();
  final FocusNode _emailNode = FocusNode();
  final FocusNode _phoneNode = FocusNode();
  final FocusNode _countryNode = FocusNode();
  final FocusNode _cityNode = FocusNode();
  final FocusNode _zipCodeNode = FocusNode();
  final FocusNode _addressNode = FocusNode();

  String? _countryDialCode = '+880';

  @override
  void initState() {
    super.initState();
    _countryDialCode = CountryCode.fromCountryCode(
      Provider.of<SplashController>(context, listen: false).configModel!.countryCode!,
    ).dialCode;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBarWidget(
        title: getTranslated('add_new_customer', context),
        isBackButtonExist: true,
      ),
      body: Consumer<CustomerManagementController>(
        builder: (context, controller, _) {
          return Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    children: [
                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      _field(_fName, _fNameNode, _lNameNode, getTranslated('first_name', context)),
                      _field(_lName, _lNameNode, _emailNode, getTranslated('last_name', context)),
                      _field(_email, _emailNode, _phoneNode, getTranslated('email_address', context)),
                      Container(
                        margin: const EdgeInsets.only(
                          left: Dimensions.paddingSizeLarge,
                          right: Dimensions.paddingSizeLarge,
                          bottom: Dimensions.paddingSizeSmall,
                        ),
                        child: Row(
                          children: [
                            CodePickerWidget(
                              onChanged: (CountryCode code) {
                                _countryDialCode = code.dialCode;
                              },
                              initialSelection: _countryDialCode,
                              favorite: [_countryDialCode!],
                              showDropDownButton: true,
                              padding: EdgeInsets.zero,
                              showFlagMain: true,
                              textStyle: Theme.of(context).textTheme.displayMedium!.copyWith(
                                    fontSize: Dimensions.fontSizeDefault,
                                    color: Theme.of(context).textTheme.bodyLarge!.color,
                                  ),
                            ),
                            Expanded(
                              child: CustomTextFieldWidget(
                                border: true,
                                hintText: getTranslated('phone', context),
                                focusNode: _phoneNode,
                                nextNode: _countryNode,
                                controller: _phone,
                                textInputType: TextInputType.phone,
                                textInputAction: TextInputAction.next,
                                formProduct: true,
                              ),
                            ),
                          ],
                        ),
                      ),
                      _field(_country, _countryNode, _cityNode, getTranslated('country', context)),
                      _field(_city, _cityNode, _zipCodeNode, getTranslated('city', context)),
                      _field(_zipCode, _zipCodeNode, _addressNode, getTranslated('zip', context)),
                      _field(_address, _addressNode, _addressNode, getTranslated('address', context), isLast: true),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: controller.isSaving
                    ? const CircularProgressIndicator()
                    : CustomButtonWidget(
                        btnTxt: getTranslated('add', context),
                        onTap: () => _submit(context, controller),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    FocusNode focusNode,
    FocusNode nextNode,
    String? hint, {
    bool isLast = false,
  }) {
    return Container(
      margin: const EdgeInsets.only(
        left: Dimensions.paddingSizeLarge,
        right: Dimensions.paddingSizeLarge,
        bottom: Dimensions.paddingSizeSmall,
      ),
      child: CustomTextFieldWidget(
        border: true,
        hintText: hint,
        focusNode: focusNode,
        nextNode: nextNode,
        controller: controller,
        textInputType: TextInputType.text,
        textInputAction: isLast ? TextInputAction.done : TextInputAction.next,
        formProduct: true,
      ),
    );
  }

  Future<void> _submit(BuildContext context, CustomerManagementController controller) async {
    final firstName = _fName.text.trim();
    final lastName = _lName.text.trim();
    final email = _email.text.trim();
    final phone = _phone.text.trim();
    final country = _country.text.trim();
    final city = _city.text.trim();
    final zip = _zipCode.text.trim();
    final address = _address.text.trim();

    if (firstName.isEmpty) {
      showCustomSnackBarWidget(getTranslated('first_name_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (lastName.isEmpty) {
      showCustomSnackBarWidget(getTranslated('last_name_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (email.isEmpty) {
      showCustomSnackBarWidget(getTranslated('email_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (EmailChecker.isNotValid(email)) {
      showCustomSnackBarWidget(getTranslated('email_is_ot_valid', context), context, sanckBarType: SnackBarType.warning);
    } else if (phone.isEmpty) {
      showCustomSnackBarWidget(getTranslated('phone_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (country.isEmpty) {
      showCustomSnackBarWidget(getTranslated('country_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (city.isEmpty) {
      showCustomSnackBarWidget(getTranslated('city_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (zip.isEmpty) {
      showCustomSnackBarWidget(getTranslated('zip_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else if (address.isEmpty) {
      showCustomSnackBarWidget(getTranslated('address_is_required', context), context, sanckBarType: SnackBarType.warning);
    } else {
      final saved = await controller.addCustomer(
        context,
        CustomerBody(
          fName: firstName,
          lName: lastName,
          email: email,
          phone: '$_countryDialCode$phone',
          country: country,
          city: city,
          zipCode: zip,
          address: address,
        ),
      );
      if (saved && context.mounted) {
        Navigator.pop(context);
      }
    }
  }
}
