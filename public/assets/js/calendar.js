document.addEventListener('DOMContentLoaded', function() {
    const calendarContainer = document.getElementById('calendar');
    const today = new Date();
    let currentMonth = today.getMonth();
    let currentYear = today.getFullYear();

    function generateCalendar(month, year) {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay();

        const monthNames = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                          "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];

        let calendarHTML = `
            <div class="calendar-header d-flex justify-content-between align-items-center mb-2">
                <button class="btn btn-sm btn-outline-secondary prev-month">&lt;</button>
                <h6 class="mb-0">${monthNames[month]} ${year}</h6>
                <button class="btn btn-sm btn-outline-secondary next-month">&gt;</button>
            </div>
            <table class="table table-sm table-bordered text-center">
                <thead>
                    <tr>
                        <th>L</th>
                        <th>M</th>
                        <th>M</th>
                        <th>J</th>
                        <th>V</th>
                        <th>S</th>
                        <th>D</th>
                    </tr>
                </thead>
                <tbody>
        `;

        let date = 1;
        for (let i = 0; i < 6; i++) {
            let row = '<tr>';
            
            for (let j = 0; j < 7; j++) {
                if (i === 0 && j < startingDay) {
                    row += '<td></td>';
                }
                else if (date > daysInMonth) {
                    break;
                }
                else {
                    const isToday = date === today.getDate() && 
                                  month === today.getMonth() && 
                                  year === today.getFullYear();
                    const hasEvent = false; // À implémenter avec les vrais événements
                    
                    row += `<td class="${isToday ? 'bg-success text-white' : ''} ${hasEvent ? 'has-event' : ''}">${date}</td>`;
                    date++;
                }
            }
            
            row += '</tr>';
            calendarHTML += row;
            
            if (date > daysInMonth) {
                break;
            }
        }

        calendarHTML += '</tbody></table>';
        calendarContainer.innerHTML = calendarHTML;

        // Event listeners pour la navigation
        document.querySelector('.prev-month').addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateCalendar(currentMonth, currentYear);
        });

        document.querySelector('.next-month').addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateCalendar(currentMonth, currentYear);
        });
    }

    generateCalendar(currentMonth, currentYear);
});
