#---------------------------
# This script generates a new pmpro-addon-packages.pot file for use in translations.
# To generate a new pmpro-addon-packages.pot, cd to the main /pmpro-addon-packages/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmpro-addon-packages.pot open before running script above)
# then execute `cp languages/pmpro-addon-packages.pot languages/pmpro-addon-packages.po` to copy the .pot to .po
# then execute `msgfmt languages/pmpro-addon-packages.po --output-file languages/pmpro-addon-packages.mo` to generate the .mo
#---------------------------
echo "Updating pmpro-addon-packages.pot... "
xgettext -j -o languages/pmpro-addon-packages.pot \
--default-domain=pmpro-addon-packages \
--language=PHP \
--keyword=_ \
--keyword=__ \
--keyword=_e \
--keyword=_ex \
--keyword=_n \
--keyword=_x \
--sort-by-file \
--package-version=1.0 \
--msgid-bugs-address="info@paidmembershipspro.com" \
$(find . -name "*.php")
echo "Done!"