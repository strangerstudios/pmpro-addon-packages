#---------------------------
# This script generates a new pmproap.pot file for use in translations.
# To generate a new pmproap.pot, cd to the main /pmpro-addon-packages/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmproap.pot open before running script above)
# then execute `cp languages/pmproap.pot languages/pmproap.po` to copy the .pot to .po
# then execute `msgfmt languages/pmproap.po --output-file languages/pmproap.mo` to generate the .mo
#---------------------------
echo "Updating pmproap.pot... "
xgettext -j -o languages/pmproap.pot \
--default-domain=pmproap \
--language=PHP \
--keyword=_ \
--keyword=__ \
--keyword=_e \
--keyword=_ex \
--keyword=_n \
--keyword=_x \
--sort-by-file \
--package-version=1.0 \
--msgid-bugs-address="jason@strangerstudios.com" \
$(find . -name "*.php")
echo "Done!"