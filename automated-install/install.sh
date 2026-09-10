#!/usr/bin/env bash

# facileManager: Easy System Administration
# Copyright (C) The facileManager Team (http://www.facilemanager.com)
#
# Installs facileManager and modules

# https://www.facilemanager.com/donate/
#
# Install the module client with this command (from your client machine):
#
# curl -sSL https://install.facilemanager.com | bash
#
# Example with passing install flags:
# curl -sSL https://install.facilemanager.com | sudo FM_INSTALL_MODULE=fmDNS FM_UPDATE_METHOD=cron bash
#
# Script inspiration from:
# - https://raw.githubusercontent.com/pi-hole/pi-hole/master/automated%20install/basic-install.sh

# Steps:
#  - Check for root
#  - Check OS for script support
#  - Check/install dependent apps
#  - Install facileManager-core
#  - Prompt for module to install
#  - Install module files
#  - Run `sudo php client.php install`

# Supported installation flags:
# FM_SKIP_SELINUX_CHECK (true|false)
# FM_SKIP_OS_CHECK (true|false)
# FM_SKIP_DEP_CHECK (true|false)
# FM_SKIP_ALL_CHECKS (true|false)
# FM_INSTALL_MODULE (fmDHCP|fmDNS|fmFirewall|fmWifi)
# FM_HOST (string)
# FM_SERIALNO (positive integer (0 for autogenerate))
# FM_UPDATE_METHOD (cron|ssh|http)

# Variables:
# Global variables are all uppercase
# Local variables are all lowercase
INSTALL_DIR_CLIENT='/usr/local/facileManager'

# Colors
COLOR_NONE='\e[0m'
COLOR_LIGHT_RED='\e[1;31m'
COLOR_LIGHT_GREEN='\e[1;32m'
COLOR_GREY='\e[0;30m'
COLOR_YELLOW='\E[33m'
COLOR_MAGENTA='\E[35m'
COLOR_CYAN='\E[36m'
COLOR_BOLD='\033[1m'
COLOR_NO_BOLD='\033[0m'
# Icons
PASS="(${COLOR_LIGHT_GREEN}\U2713${COLOR_NONE})"
FAIL="(${COLOR_LIGHT_RED}\U2718${COLOR_NONE})"
INFO='(i)'
DASH='(-)'
QUESTION='(?)'
DONE="${COLOR_LIGHT_GREEN}Client installation is complete!${COLOR_NONE}"
# Write over previous line
OVER="\\r\\033[K"

# ================================================================ #

findProgram() {
    command -v "${1}" >/dev/null 2>&1
}

# Check if SELinux is enforcing
checkSELinux() {
    local default_selinux
    local current_selinux
    local selinux_enforcing=0
    local str='Checking SELinux'

    printf "\\n  %b %s" "${DASH}" "${str}"

    # Are we skipping this check?
    if [[ -n $FM_SKIP_ALL_CHECKS && "$FM_SKIP_ALL_CHECKS" = true ]]; then
        printf "%b  %b %s\\n" "${OVER}" "${QUESTION}" "${str}"
        printf "  %b %bFM_SKIP_ALL_CHECKS env variable set - Skipping%b\\n" "${INFO}" "${COLOR_YELLOW}" "${COLOR_NONE}"
        return
    fi

    # Check for SELinux configuration file and getenforce command
    if [[ -f /etc/selinux/config ]] && findProgram getenforce; then
        # Check the default SELinux mode
        default_selinux=$(awk -F= '/^SELINUX=/ {print $2}' /etc/selinux/config)
        case "${default_selinux,,}" in
        enforcing)
            printf "  %b %bDefault SELinux: %s%b\\n" "${FAIL}" "${COLOR_LIGHT_RED}" "${default_selinux,,}" "${COLOR_NONE}"
            selinux_enforcing=1
            ;;
        *) # 'permissive' and 'disabled'
            printf "  %b %bDefault SELinux: %s%b\\n" "${PASS}" "${COLOR_LIGHT_GREEN}" "${default_selinux,,}" "${COLOR_NONE}"
            ;;
        esac
        # Check the current state of SELinux
        current_selinux=$(getenforce)
        case "${current_selinux,,}" in
        enforcing)
            printf "  %b %bCurrent SELinux: %s%b\\n" "${FAIL}" "${COLOR_LIGHT_RED}" "${current_selinux,,}" "${COLOR_NONE}"
            selinux_enforcing=1
            ;;
        *) # 'permissive' and 'disabled'
            printf "  %b %bCurrent SELinux: %s%b\\n" "${PASS}" "${COLOR_LIGHT_GREEN}" "${current_selinux,,}" "${COLOR_NONE}"
            ;;
        esac
    else
        printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
        printf "  %b %bSELinux is not detected%b\\n" "${INFO}" "${COLOR_LIGHT_GREEN}" "${COLOR_NONE}"
    fi
    # Exit the installer if any SELinux checks toggled the flag
    if [[ "${selinux_enforcing}" -eq 1 ]] && [[ -z ${FM_SKIP_SELINUX_CHECK} ]]; then
        printf "  facileManager does not provide an SELinux policy.\\n"
        printf "  Please refer to https://wiki.centos.org/HowTos/SELinux if SELinux is required for your deployment.\\n"
        printf "      This check can be skipped by setting the environment variable to true\\n"
        printf "        %bexport FM_SKIP_SELINUX_CHECK=true%b\\n" "${COLOR_CYAN}" "${COLOR_NONE}"
        printf "      By setting this variable to true you acknowledge there may be issues with facileManager (including modules) during or after the install\\n"
        printf "\\n  %bSELinux Enforcing is detected, exiting.%b\\n" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        exit 1
    elif [[ "${selinux_enforcing}" -eq 1 ]] && [[ -n "${FM_SKIP_SELINUX_CHECK}" ]]; then
        printf "  %b %bSELinux Enforcing is detected%b.\\n" "${INFO}" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        printf "  %b %bFM_SKIP_SELINUX_CHECK env variable set - continuing with installation\\n" "${INFO}" "${COLOR_YELLOW}" "${COLOR_NONE}"
    fi
}

# Check for a supported OS for the installer
checkSupportedOS() {
    local str='Checking OS compatibility'

    printf "\\n  %b %s" "${DASH}" "${str}"

    # Are we skipping this check?
    if [[ -n $FM_SKIP_OS_CHECK && "$FM_SKIP_OS_CHECK" = true ]] || [[ -n $FM_SKIP_ALL_CHECKS && "$FM_SKIP_ALL_CHECKS" = true ]]; then
        printf "%b  %b %s\\n" "${OVER}" "${QUESTION}" "${str}"
        printf "  %b %bFM_SKIP_OS_CHECK or FM_SKIP_ALL_CHECKS env variable set - Skipping%b\\n" "${INFO}" "${COLOR_YELLOW}" "${COLOR_NONE}"
        return
    fi

    # To be written for future use
    
    printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
    return
}

# Get the package manager
detectPackageManager() {
    local packages="$@"
    local str='Detecting package manager'

    printf "\\n  %b %s" "${DASH}" "${str}"

    # Are we skipping this check?
    if [[ -n $FM_SKIP_DEP_CHECK && "$FM_SKIP_DEP_CHECK" = true ]] || [[ -n $FM_SKIP_ALL_CHECKS && "$FM_SKIP_ALL_CHECKS" = true ]]; then
        printf "%b  %b %s\\n" "${OVER}" "${QUESTION}" "${str}"
        printf "  %b %bFM_SKIP_DEP_CHECK or FM_SKIP_ALL_CHECKS env variable set - Skipping%b\\n" "${INFO}" "${COLOR_YELLOW}" "${COLOR_NONE}"
        return
    fi

    # Check if apt-get exists
    if findProgram apt-get; then
        PKG_MANAGER='apt-get'
        # The install command
        PKG_INSTALL="${PKG_MANAGER} -qq --no-install-recommends install"
        # Update the package cache
    
    # Check if rpm exists
    elif findProgram rpm; then
        # Do we have dnf or yum?
        if findProgram dnf; then
            PKG_MANAGER='dnf'
        elif findProgram yum; then
            PKG_MANAGER='yum'
        fi
        PKG_INSTALL="${PKG_MANAGER} install -y"
    fi
    
    # Nothing else is supported so exit
    if [ -z $PKG_MANAGER ]; then
        printf "\\n  %b No supported package manager was found. Please install %b%s%b and re-run the installer with FM_SKIP_DEP_CHECK set to true.\\n" "${FAIL}" "${COLOR_BOLD}" "${packages[@]}" "${COLOR_NO_BOLD}"
        printf "        %bexport FM_SKIP_DEP_CHECK=true%b\\n\\n" "${COLOR_CYAN}" "${COLOR_NONE}"
        exit 1
    fi

    printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
    printf "  %b Found %s\\n\\n" "${INFO}" "${PKG_MANAGER}"
}

functionalCheck() {
    local dependencies=('curl' 'tar')
    local install_error=0

    for program in "${dependencies[@]}"; do
        local str="Checking for ${program}"
        printf "  %b %s" "${DASH}" "${str}"

        if findProgram "${program}"; then
            printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
        else
            printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"

            # Make sure we can install packages
            detectPackageManager "${dependencies[@]}"

            installDependentPackage "${program}"
            if [ $? -ne 0 ]; then
                install_error=1
            fi
        fi
    done

    if [ "${install_error}" -eq 1 ]; then
        printf "  %b %s are required to run this installer. Please install %b%s%b and then re-run the installer.\\n" "${FAIL}" "${dependencies[@]}" "${COLOR_BOLD}" "${dependencies[@]}" "${COLOR_NO_BOLD}"
        exit 1
    fi

    checkSELinux

    checkSupportedOS
}

# Install any package dependencies
installDependentPackage() {
    local package="$1"
    local str="Installing dependency package (${package})"
    printf "  %b %s" "${INFO}" "${str}"

    if eval "${PKG_INSTALL}" "${package}" &>/dev/null; then
        printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
    else
        printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"
        printf "     %b Error: Unable to install dependency package.%b\\n" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        return 1
    fi
}

# Check if a download link exists
checkDownloadLink() {
    # Check if the download exists and we can reach the server
    local status=$(curl --head --silent "${1}" | head -n 1)

    # Check the status code
    if grep -q '200' <<<"$status"; then
        return 0
    elif grep -q '404' <<<"$status"; then
        return 1
    fi

    # Catch-all for any other status code
    return 2
}

# Download a file
downloadFile() {
    local file_link="$1"
    local rc
    local str="Downloading ${file_link}"
    printf "  %b %s" "${DASH}" "${str}"

    # Check if the download link is valid
    checkDownloadLink "$file_link"
    rc=$?
    if [ $rc -ne 0 ]; then
        printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"
        if [ $rc -eq 1 ]; then
            printf "  %b Package is not found on www.facilemanager.com.\\n" "${INFO}"
            return 2
        elif [ $rc -eq 2 ]; then
            printf "  %b Unable to download from www.facilemanager.com. Please check your Internet connection and try again later.\\n" "${FAIL}"
            return 3
        else
            printf "  %b Unknown error.\\n" "${FAIL}"
            return 4
        fi
    fi

    # It's valid so download the file
    if eval "curl -s -O" "${file_link}" &>/dev/null; then
        printf "%b  %b %b%s%b\\n" "${OVER}" "${PASS}" "${COLOR_GREY}" "${str}" "${COLOR_NONE}"
    else
        printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"
        printf "  %b Unable to save to /tmp.\\n" "${INFO}"
        return 5
    fi
}

# Extract tar file
extractTarFile() {
    local file_name="$1"
    local str='Extracting package in /tmp'

    printf "  %b %s" "${DASH}"
    if eval "tar zxf" "${file_name}" 2>/dev/null; then
        printf "%b  %b %b%b%b\\n" "${OVER}" "${PASS}" "${COLOR_GREY}" "${str}" "${COLOR_NONE}"
    else
        printf "%b  %b %b\\n" "${OVER}" "${FAIL}" "${str}"
        return 1
    fi
}

# Copy directory contents
copyDirectoryContents() {
    local source_dir="$1"
    local dest_dir="$2"
    local str='Copying package contents to installation directory'

    printf "  %b %s" "${DASH}"
    if eval "cp -r" "${source_dir}" "${dest_dir}" 2>/dev/null; then
        printf "%b  %b %b%b%b\\n" "${OVER}" "${PASS}" "${COLOR_GREY}" "${str}" "${COLOR_NONE}"
    else
        printf "%b  %b %b\\n" "${OVER}" "${FAIL}" "${str}"
        return 1
    fi
}

# Remove files
removeFiles() {
    local file_name="$1"
    local str='Cleaning up temporary files'

    printf "  %b %s" "${DASH}"
    if eval "rm -rf" "${file_name}" 2>/dev/null; then
        printf "%b  %b %b%b%b\\n" "${OVER}" "${PASS}" "${COLOR_GREY}" "${str}" "${COLOR_NONE}"
    else
        printf "%b  %b %b\\n" "${OVER}" "${FAIL}" "${str}"
        return 1
    fi
}

# Dig query to get the available modules
digAvailableModuleList() {
    local ipv="$1"
    local domain="$2"
    local ns="$3"
    local response

    # Are we using dig or nslookup?
    if findProgram dig; then
        if [ -n "${ns}" ]; then
            ns="@{$ns}"
        fi
        response="$(dig +short -"${ipv}" -t txt "${domain}" ${ns} 2>&1
        echo $?
        )"
    elif findProgram nslookup; then
        # Use nslookup instead of dig
        response="$(nslookup -query=TXT "${domain}" ${ns} | grep 'text = ' | awk '{print $NF}' 2>&1
        echo $?
        )"
    fi

    echo "${response}"
}

checkDigResponse() {
    local dig_result="$1"
    local dig_rc

    # Get the return code (last line)
    dig_rc="${dig_result##*$'\n'}"

    if [ ! "${dig_rc}" == "0" ]; then
        echo false
    else
        # The first line of ${dig_result} should not be 0 otherwise this is the return code
        if [ "${dig_result%%$'\n'*}" == 0 ]; then
            echo false
        else
            echo true
        fi
    fi
}

inArray() {
    local needle="$1"
    local haystack=("${@:2}")
    local e
    for e in "${haystack[@]}"; do
        if [ $(grep -ix "$e" <<< "$needle") ]; then
            echo "$e"
            return
        fi
    done
    echo false
}

# Build an array of available modules
getAvailableModuleList() {
    # Get available list from dns query since not all modules have a client
    local dig_result valid_response response
    local domain_to_query='install.facilemanager.com'
    local hardcoded_ns='dns1.namecheaphosting.com'
    local str='Retrieving available module list'
    printf "\\n  %b %s" "${DASH}" "${str}"

    # Test with IPv4 and a hard-coded name server
    # dig_result=$(digAvailableModuleList 4 "${domain_to_query}" "@${hardcoded_ns}")
    # valid_response=$(checkDigResponse "${dig_result}")
    valid_response=false

    # Try without hard-coded nameserver if previous is invalid
    if [ "$valid_response" = false ]; then
        unset valid_response
        unset dig_result

        dig_result=$(digAvailableModuleList 4 "${domain_to_query}")
        valid_response=$(checkDigResponse "${dig_result}")
    fi

    # Try with IPv6 and hard-coded nameserver if previous is invalid
    if [ "$valid_response" = false ]; then
        unset valid_response
        unset dig_result

        dig_result=$(digAvailableModuleList 6 "${domain_to_query}" "${hardcoded_ns}")
        valid_response=$(checkDigResponse "${dig_result}")
    fi

    # Try without hard-coded nameserver if previous is invalid
    if [ "$valid_response" = false ]; then
        unset valid_response
        unset dig_result

        dig_result=$(digAvailableModuleList 6 "${domain_to_query}")
        valid_response=$(checkDigResponse "${dig_result}")
    fi

    # Return the availableModules array
    if [ "$valid_response" = true ]; then
        response="${dig_result%%$'\n'*}"
        IFS="," read -r -a AVAILABLE_MODULES < <(echo "${response}" | tr -d '"')

        printf "%b  %b %s\\n\\n" "${OVER}" "${PASS}" "${str}"
    else
        # Otherwise display messaging
        printf "\\n  %b %bCould not retrieve the list of available modules from %s%b.\\n" "${FAIL}" "${COLOR_LIGHT_RED}" "${domain_to_query}" "${COLOR_NONE}"
        printf "      Possible causes include:\\n"
        printf "        - A local firewall is blocking DNS lookups to %s\\n" "${hardcoded_ns}"
        printf "        - This system does not have dig nor nslookup installed\\n"
        printf "        - This system has general DNS resolution problems\\n"
        printf "        - Some other Internet connectivity issue\\n\\n"
        exit 1
    fi
}

# Install facileManager
installCore() {
    local dependencies=('php-cli' 'php-curl' 'php-openssl' 'php-zlib')
    local fm_core_package='facilemanager-core-latest.tar.gz'
    local fm_core_root_path=$(dirname ${INSTALL_DIR_CLIENT})

    # Make sure we can install packages
    if [ -z $PKG_MANAGER ]; then
        detectPackageManager "${dependencies[@]}"
    fi

    local str='Checking for package dependencies'
    local install_error=0

    printf "\\n  %b %s" "${DASH}" "${str}"

    # Are we skipping this?
    if [[ -n $FM_SKIP_DEP_CHECK && "$FM_SKIP_DEP_CHECK" = true ]] || [[ -n $FM_SKIP_ALL_CHECKS && "$FM_SKIP_ALL_CHECKS" = true ]]; then
        printf "%b  %b %s\\n" "${OVER}" "${QUESTION}" "${str}"
        printf "  %b %bFM_SKIP_DEP_CHECK or FM_SKIP_ALL_CHECKS env variable set - Skipping%b\\n" "${INFO}" "${COLOR_YELLOW}" "${COLOR_NONE}"
    else
        echo
        
        # Check if php exists
        if ! findProgram php; then
            installDependentPackage "php-cli"
            if [ $? -ne 0 ]; then
                install_error=1
            fi
        fi

        # Remove php-cli from dependencies since it's already installed
        dependencies=("${dependencies[@]/php-cli}")

        # Check php module dependencies and install if missing
        if [ "${install_error}" -eq 0 ]; then
            for p in "${dependencies[@]}"; do
                if ! php -m | grep -q "^${p##php-}$"; then
                    installDependentPackage "${p}"
                    if [ $? -ne 0 ]; then
                        install_error=1
                    fi
                fi
            done
        fi
    fi
    if [ "${install_error}" -eq 1 ]; then
        printf "  %b One or more dependency packages could not be installed.\\n" "${FAIL}"
        printf "      Please install the missing packages and then re-run the installer.\\n"
        printf "      This check can be skipped by setting the environment variable to true\\n"
        printf "        %bexport FM_SKIP_DEP_CHECK=true%b\\n\\n" "${COLOR_CYAN}" "${COLOR_NONE}"
        exit 1
    fi

    # Move into the tmp directory
    pushd /tmp &>/dev/null || return 1

    # Delete any previous temporary install files
    removeFiles 'facileManager'

    printf "\\n  %b Installing facileManager-core files...\\n" "${DASH}"

    # Already installed!
    if [[ -d "${INSTALL_DIR_CLIENT}" && -f "${INSTALL_DIR_CLIENT}/functions.php" ]]; then
        printf "  %b %bAlready installed!%b\\n" "${PASS}" "${COLOR_LIGHT_GREEN}" "${COLOR_NONE}"
    else
        # Download core package
        if ! downloadFile "https://www.facilemanager.com/download/${fm_core_package}"; then
            exit $?
        fi
        
        # Extract core package
        if ! extractTarFile "${fm_core_package}"; then
            exit $?
        fi

        # Move the client files
        if ! copyDirectoryContents 'facileManager/client/facileManager' "${fm_core_root_path}/"; then
            exit $?
        fi

        # Clean up temp files
        if ! removeFiles "facileManager ${fm_core_package}"; then
            exit $?
        fi
    fi

    printf "\\n"

    # Move back into the directory the user started in
    popd &> /dev/null || return 1
}

# Install modules
installModule() {
    local module selected_module selection_error
    local str='Checking for facileManager-core files'
    printf "\\n  %b %s" "${DASH}" "${str}"

    while [[ ! -d "${INSTALL_DIR_CLIENT}" && ! -f "${INSTALL_DIR_CLIENT}/functions.php" ]]; do
        printf "\\n  %b %bfacileManager is not installed! Attempting to install the core package.%b\\n" "${FAIL}" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        installCore
    done

    printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"

    # Populate $AVAILABLE_MODULES
    getAvailableModuleList

    # Check if $FM_INSTALL_MODULE is valid
    if [ ! -z $FM_INSTALL_MODULE ]; then
        str='Validating FM_INSTALL_MODULE'
        printf "  %b %s" "${DASH}" "${str}"
        selected_module=$(inArray "${FM_INSTALL_MODULE}" "${AVAILABLE_MODULES[@]}")
        if [ "${selected_module}" != false ]; then
            # Set proper casing for $FM_INSTALL_MODULE
            FM_INSTALL_MODULE=${selected_module}
            printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"
        else
            printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"
            printf "  %b %s is not a valid module\\n" "${INFO}" "${FM_INSTALL_MODULE}"
            unset FM_INSTALL_MODULE
        fi
    fi

    if [ -z $FM_INSTALL_MODULE ]; then
        printf "  %b FM_INSTALL_MODULE is not detected. Prompting for module installation.\\n\\n" "${INFO}"
    fi

    # Prompt for module to install
    while [ -z $FM_INSTALL_MODULE ]; do
        # Display module selection
        echo 'Listing available modules'
        for (( i = 0 ; i < ${#AVAILABLE_MODULES[@]} ; i++ )); do
            echo "$i: ${AVAILABLE_MODULES[$i]}"
        done
        echo
        read -p "Enter the number for the module to install ('q' quits) [${AVAILABLE_MODULES[0]}]: " module < /dev/tty
        module=$(echo "$module" | awk '{print tolower($0)}')
        if [ "$module" == 'q' ]; then
            exit
        fi
        if [ "$module" == '' ]; then
            module=0
        fi
        if [[ $(echo $module | sed 's/ //g' | sed 's/^[0-9]*//' | wc -c) -ne 1 ]]; then
            module=-1
        fi
        # Ensure only one entry was selected
        selected_module=( $(echo $module) )
        if [ 1 -lt ${#selected_module[@]} ]; then
            module=-1
        fi
        selection_error=0
        # Make sure the module exists
        if [[ $module -lt 0 || $module -ge ${#AVAILABLE_MODULES[@]} ]]; then
            selection_error=1
        else
            FM_INSTALL_MODULE=${AVAILABLE_MODULES[$module]}
        fi

        [[ $selection_error -eq 1 ]] && FM_INSTALL_MODULE=""
        echo
    done

    local fm_module_package="${FM_INSTALL_MODULE}-latest.tar.gz"
    fm_module_package=$(echo "$fm_module_package" | awk '{print tolower($0)}')

    # Move into the tmp directory
    pushd /tmp &>/dev/null || return 1

    # Delete any previous temporary install files
    removeFiles 'facileManager'

    printf "\\n  %b Installing ${FM_INSTALL_MODULE} files...\\n" "${DASH}"
    # Already installed!
    if [[ -d "${INSTALL_DIR_CLIENT}/${FM_INSTALL_MODULE}" && -f "${INSTALL_DIR_CLIENT}/${FM_INSTALL_MODULE}/functions.php" ]]; then
        printf "  %b %bAlready installed!%b\\n" "${PASS}" "${COLOR_LIGHT_GREEN}" "${COLOR_NONE}"
    else
        # Download core package
        if ! downloadFile "https://www.facilemanager.com/download/module/${fm_module_package}"; then
            exit $?
        fi
        
        # Extract core package
        if ! extractTarFile "${fm_module_package}"; then
            exit $?
        fi

        # Move the client files
        if ! copyDirectoryContents "facileManager/client/facileManager/${FM_INSTALL_MODULE}" "${INSTALL_DIR_CLIENT}"; then
            exit $?
        fi

        # Clean up temp files
        if ! removeFiles "facileManager ${fm_module_package}"; then
            exit $?
        fi
    fi

    # Move back into the directory the user started in
    popd &> /dev/null || return 1
}

# Run client installer
runClientInstaller() {
    local install_options

    # Utilize $FM_HOST
    if [ ! -z $FM_HOST ]; then
        install_options="${install_options},FMHOST=${FM_HOST}"
    fi

    # Utilize $FM_SERIALNO
    if [ ! -z $FM_SERIALNO ]; then
        install_options="${install_options},SERIALNO=${FM_SERIALNO}"
    fi

    # Utilize $FM_UPDATE_METHOD
    if [ ! -z $FM_UPDATE_METHOD ]; then
        install_options="${install_options},method=${FM_UPDATE_METHOD}"
    fi

    # Format $install_options
    if [ ! -z $install_options ]; then
        if [[ ${install_options:0:1} == ',' ]]; then
            install_options="${install_options:1}"
        fi

        install_options="-o ${install_options}"
    fi

    # Launch module installer
    printf "\\n  %b %bLaunching ${FM_INSTALL_MODULE} Installer...%b\\n\\n" "${DASH}" "${COLOR_CYAN}" "${COLOR_NONE}"
    if findProgram php; then
        php ${INSTALL_DIR_CLIENT}/${FM_INSTALL_MODULE}/client.php install ${install_options} < /dev/tty
    else
        printf "  %b %bCould not find php. Please install php and then run the installer manually.%b\\n" "${FAIL}" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        printf "        %bsudo php ${INSTALL_DIR_CLIENT}/${FM_INSTALL_MODULE}/client.php install ${install_options}%b\\n\\n" "${COLOR_CYAN}" "${COLOR_NONE}"
        exit 1
    fi

    return
}

# Must be root
str='Checking for root user'
printf "\\n%b%bWelcome to the facileManager app installer%b%b\\n\\n" "${COLOR_CYAN}" "${COLOR_BOLD}" "${COLOR_NO_BOLD}" "${COLOR_NONE}"

# If the user's id is zero,
if [[ "${EUID}" -eq 0 ]]; then
    # they are root
    printf "  %b %s\\n" "${PASS}" "${str}"
else
    # Otherwise, they do not have enough privileges, so let the user know
    printf "  %b %s\\n" "${INFO}" "${str}"
    printf "  %b %bScript called with non-root privileges%b\\n" "${INFO}" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
    printf "      The facileManager installer requires elevated privileges\\n\\n"

    str='Checking for sudo'
    printf "  %b %s" "${INFO}" "${str}"

    # If the sudo command exists, try re-running as admin
    if findProgram sudo; then
        printf "%b  %b %s\\n" "${OVER}" "${PASS}" "${str}"

        # when run via curl piping
        if [[ "$0" == 'bash' ]]; then
            # Download the install script and run it with admin rights if curl is installed
            if ! findProgram curl; then
                printf "  %b Unable to find curl. Please install %bcurl%b and then re-run the installer.%b\\n" "${FAIL}" "${COLOR_BOLD}" "${COLOR_NO_BOLD}"
                exit 1
            fi
            exec curl -sSL https://install.facilemanager.com | sudo bash "$@"
        else
            # when run via calling local bash script
            exec sudo bash "$0" "$@"
        fi

        exit $?
    else
        # Otherwise, tell the user they need to run the script as root, and bail
        printf "%b  %b %s\\n" "${OVER}" "${FAIL}" "${str}"
        printf "  %b Sudo is needed to install the app in root-protected directories\\n\\n" "${INFO}"
        printf "  %b %bPlease re-run this installer as root%b\\n" "${INFO}" "${COLOR_LIGHT_RED}" "${COLOR_NONE}"
        exit 1
    fi
fi

functionalCheck

installModule

runClientInstaller

printf "\\n${DONE}\\n"