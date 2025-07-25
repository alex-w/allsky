#!/bin/bash

[[ -z ${ALLSKY_HOME} ]] && export ALLSKY_HOME="$( realpath "$(dirname "${BASH_ARGV0}")/../.." )"
ME="$( basename "${BASH_ARGV0}" )"

#shellcheck source-path=.
source "${ALLSKY_HOME}/variables.sh"					|| exit "${EXIT_ERROR_STOP}"
#shellcheck source-path=scripts
source "${ALLSKY_SCRIPTS}/functions.sh"					|| exit "${EXIT_ERROR_STOP}"

if [[ "$( settings ".startrailsgenerate" )" != "true" ]]; then
	w_ "\nWARNING: The startrails 'Generate' setting is not enabled."
fi

# Input format:
# 2025-01-17T06:20:45.240112-06:00 \
#	Minimum: 0.0840083   maximum: 0.145526   mean: 0.103463   median: 0.104839
#	$2       $3          $4       $5         $6    $7         $8      $9

LOGS="$( ls -tr "${ALLSKY_LOG}"* )"
#shellcheck disable=SC2086
grep --no-filename "startrails: Minimum" ${LOGS} 2> /dev/null |
	sed "s/$(uname -n).*startrails: //" |
	nawk 'BEGIN {
			print;
			t_min=0; t_max=0; t_mean=0; t_median=0; t_num=0; t_used=0; t_notUsed=0;
			numFmt = "%-20s   %.3f     %.3f     %.3f     %.3f     %-9d     %-d\n";
		}
		{
			if (++num == 1) {
				header = sprintf("%-20s   %-8s  %-8s  %-8s  %-8s  %-12s  %-s\n",
					"Date", "Minimum", "Maximum", "Mean", "Median", "Images used", "Not used");
				printf(header);
				dashes = "-";
				l = length(header) - 2;
				for (i=1; i<=l; i++) {
					dashes = dashes "-";
				}
				printf("%s\n", dashes);
			}


			date = substr($1, 0, 10) "  "  substr($1, 12, 8);
			min = $3;		t_min += min;
			max = $5;		t_max += max;
			mean = $7;		t_mean += mean;
			median = $9;	t_median += median;
			used = $11;		t_used += used;
			notUsed = $11;		t_notUsed += notUsed;
			printf(numFmt, date, min, max, mean, median, used, notUsed);
		}
		END {
			if (num == 0) {
				exit 1;
			} else if (num == 1) {
				printf("\n");
				printf("Data for only 1 startrails was found.\n");
				printf("Consider running this command in a few days to get more information.\n");
			} else {
				printf("%s\n", dashes);
				printf(numFmt, "Total average", t_min/num, t_max/num, t_mean/num, t_median/num, t_used/num, t_notUsed/num);
			}
			exit 0;
		}'
if [[ $? -ne 0 ]]; then
	echo -n "No information found.  "
	STATUS="$( get_allsky_status )"
	if [[ -z ${STATUS} ]]; then
		echo "Is Allsky running?"
	else
		TIMESTAMP="$( get_allsky_status_timestamp )"
		echo "Allsky is ${STATUS} as of ${TIMESTAMP:-unknown time}."
	fi
fi
echo
