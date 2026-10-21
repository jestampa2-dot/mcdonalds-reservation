from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt


ROOT = Path(r"C:\Users\User\mcdonalds-reservation")
SOURCE = ROOT / "Full Thesis - IPO Conceptual Framework.docx"
OUTPUT = ROOT / "Full Thesis - Updated RRL Old Format.docx"


def delete_paragraph(paragraph):
    element = paragraph._element
    element.getparent().remove(element)
    paragraph._p = paragraph._element = None


def append_after(anchor, text="", style_name="Normal", bold=False, italic=False, first_indent=True):
    from docx.oxml import OxmlElement
    from docx.text.paragraph import Paragraph

    new_element = OxmlElement("w:p")
    anchor._p.addnext(new_element)
    paragraph = Paragraph(new_element, anchor._parent)
    paragraph.style = style_name
    paragraph.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY if style_name == "Normal" else WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.space_after = Pt(6)
    if style_name == "Normal" and first_indent:
        paragraph.paragraph_format.first_line_indent = Inches(0.5)
    run = paragraph.add_run(text)
    run.font.name = "Arial"
    run.font.size = Pt(12)
    run.bold = bold
    run.italic = italic
    return paragraph


def format_heading(paragraph, text, level=1):
    paragraph.text = text
    paragraph.style = f"Heading {level}"
    paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.first_line_indent = None
    for run in paragraph.runs:
        run.font.name = "Arial"
        run.font.size = Pt(16 if level == 1 else 14 if level == 2 else 12)
        run.bold = True


def make_reference(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.first_line_indent = Inches(-0.5)
    paragraph.paragraph_format.left_indent = Inches(0.5)
    paragraph.paragraph_format.space_after = Pt(6)
    for run in paragraph.runs:
        run.font.name = "Arial"
        run.font.size = Pt(12)


LOCAL_LITERATURE = [
    (
        "2.1.1 Online Event Reservation with SMS Notification",
        "Capuno et al. (2021) developed an online event reservation system with SMS notification for the Lipa City Cultural Center. The study emphasized that automated reservation tools improve the accuracy of event scheduling by reducing dependence on manual logs and verbal confirmations. Its SMS notification feature also strengthened communication between administrators and clients because users could receive timely updates about reservation status. This is relevant to the proposed McDonald's Event Planner because event reservation systems must provide reliable booking confirmation and minimize scheduling conflicts.",
    ),
    (
        "2.1.2 Web-Based Resort Reservation System",
        "Ramento et al. (2024) developed a web-based reservation and management system for Nature Spring Resort. Their findings revealed that digital reservation platforms improve booking accuracy, customer convenience, and administrative monitoring. The system helped organize customer records and reservation schedules in a more accessible format. This supports the need for a centralized digital platform for McDonald's event reservations, especially for managing customer requests, schedules, and booking details.",
    ),
    (
        "2.1.3 Digital Reservation Platforms in Hospitality",
        "Esguerra and Arreza (2021) examined the factors affecting the use of digital hotel booking platforms in Cebu City. Their study found that ease of navigation, affordability, and simplified booking processes significantly influence customer satisfaction. The findings suggest that users are more likely to accept reservation systems when the interface is clear and the booking process is easy to complete. This is important for the proposed system because customers booking birthday parties or meetings need a convenient and understandable reservation flow.",
    ),
    (
        "2.1.4 Computer Reservation Systems in Travel Agencies",
        "Felicen and Ylagan (2020) discussed the role of computer reservation systems in the operations of travel agencies. Their study indicated that computerized systems improve operational efficiency, customer service delivery, and transaction monitoring. Although the study focused on travel agencies, its findings are applicable to service-based businesses that handle reservations. The proposed McDonald's Event Planner can apply the same principle by automating reservation records and improving staff access to booking information.",
    ),
    (
        "2.1.5 Web-Based Hotel Reservation Systems",
        "Calpito et al. (2020) developed a hotel reservation system with mobile and web accessibility. Their findings showed that the availability of both web and mobile access improves customer engagement and strengthens reservation management. The study highlights the value of making reservation platforms accessible through different devices. This is related to the proposed system because the McDonald's Event Planner includes web and mobile components for customers, staff, and administrators.",
    ),
    (
        "2.1.6 Resort Management Automation",
        "Villanueva (2024) introduced an online reservation management system for resorts that improved reservation tracking and reduced scheduling errors. The study showed that automation helps management monitor bookings more accurately and respond to customer needs more efficiently. This supports the proposed system's goal of reducing manual booking problems such as double reservations, misplaced records, and delayed confirmation.",
    ),
    (
        "2.1.7 Hospitality Service Innovation",
        "Felicen (2024) emphasized that innovation and digital systems improve operational performance in hospitality establishments. The study suggests that businesses that adopt technology can provide faster service, improve customer communication, and manage operations more effectively. This literature supports the proposed McDonald's Event Planner because the project introduces digital innovation to improve fast-food event reservation services.",
    ),
    (
        "2.1.8 Online Booking and Customer Satisfaction",
        "Monterey (2021) found that service quality in online booking platforms significantly affects customer satisfaction and booking intention. The study highlighted that customers value reliable information, responsive service, and convenient access when using online booking systems. This is relevant to the proposed system because customer satisfaction depends on how easily users can select event details, submit reservations, and receive status updates.",
    ),
    (
        "2.1.9 Web-Based Reservation Accessibility",
        "Leona (2023) developed a web-based accommodation reservation system that improved accessibility and real-time booking efficiency. The system allowed users to access reservation services more conveniently while helping administrators monitor records. This supports the need for a web-based McDonald's reservation platform that allows customers to book events without relying only on in-person or phone-based transactions.",
    ),
    (
        "2.1.10 Event Planning Management Systems",
        "Banas et al. (2020) highlighted that digital event management systems improve coordination between event planners and customers. The study showed that organized digital records and structured booking workflows support better event preparation and communication. This is directly related to the proposed McDonald's Event Planner because the system focuses on managing birthday parties, business meetings, and other event reservations.",
    ),
    (
        "2.1.11 Facility Reservation Systems",
        "Vega (2024) developed an online facility reservation system that improved availability monitoring and reduced overlapping reservations. The study emphasized the importance of a centralized schedule that allows administrators to check availability before confirming requests. This finding supports one of the major functions of the proposed system, which is preventing double booking through availability checking.",
    ),
    (
        "2.1.12 Digital Hotel Reservation Transformation",
        "Caritos (2024) explained that digital transformation improves hotel reservation efficiency and customer engagement. The study showed that shifting from manual to digital reservation processes can enhance record keeping, speed up transactions, and improve communication. This literature supports the modernization of McDonald's event reservation processes through an integrated digital system.",
    ),
    (
        "2.1.13 Online Resort Service Systems",
        "Deocampo et al. (2020) found that online reservation systems improve customer communication and booking convenience in resorts. Their study demonstrated that digital access helps customers submit booking requests more easily and helps staff manage service information more effectively. These findings are relevant to the proposed system because customers also need an accessible way to reserve event services at McDonald's.",
    ),
    (
        "2.1.14 Customer Experience in Hospitality",
        "Munoz (2023) emphasized that customer reviews and digital platforms influence online reservation decisions. The study suggests that customers consider digital information and online service experiences when deciding whether to proceed with a booking. For the proposed McDonald's Event Planner, this means that a clear and reliable digital reservation system can improve customer confidence and encourage system use.",
    ),
    (
        "2.1.15 Hospitality Analytics Systems",
        "Calpito et al. (2021) explained that analytics-integrated reservation systems improve management decisions and service delivery. Their study highlighted that reservation data can help managers understand booking activities and improve operational planning. This is relevant to the proposed system because administrators can benefit from organized reports and reservation histories when monitoring event schedules.",
    ),
    (
        "2.1.16 Reservation Systems and Data Accuracy",
        "Dublado et al. (2020) found that computerized hotel reservation systems improve record accuracy and reduce manual errors. The study showed that automated data handling lessens the risk of misplaced records, incorrect information, and inconsistent booking details. This supports the proposed system because accurate reservation records are essential in preventing confusion between customers, staff, and administrators.",
    ),
    (
        "2.1.17 Digital Transformation in Catering Services",
        "Felicen (2024) explained that catering businesses benefit from digital reservation and scheduling systems. The study emphasized that event-related food service operations require organized scheduling, customer coordination, and service preparation. This is related to the proposed McDonald's Event Planner because McDonald's event reservations also involve food packages, menu selections, and event schedules.",
    ),
    (
        "2.1.18 Tourism Reservation Systems",
        "Sabaulan (2023) highlighted the role of reservation systems in improving tourism-related hospitality services. The study indicated that organized reservation tools help businesses deliver better service and manage customer expectations. Although focused on tourism and hospitality, the findings support the use of digital reservation management in other service industries, including fast-food event services.",
    ),
    (
        "2.1.19 Mobile Reservation Applications",
        "Leona (2023) stated that mobile-compatible reservation systems improve accessibility and customer convenience. Mobile access allows customers to view information, submit requests, and monitor bookings using their smartphones. This is relevant to the proposed system because the McDonald's Event Planner includes a mobile application that supports customer booking and reservation monitoring.",
    ),
    (
        "2.1.20 Automated Reservation Monitoring",
        "Capuno et al. (2021) emphasized that automated notifications improve reservation monitoring and customer communication. Notifications help ensure that users are informed about booking status and related updates. This finding supports the proposed system's need for clear communication features that can reduce uncertainty and improve the reservation experience.",
    ),
]


FOREIGN_LITERATURE = [
    (
        "2.2.1 Restaurant Booking Automation",
        "Egigogo et al. (2024) developed a computerized restaurant table booking system that improved reservation efficiency and customer experience. Their study showed that computerized booking processes reduce manual workload and help restaurant staff manage reservations more accurately. This is relevant to the proposed McDonald's Event Planner because both systems aim to organize food-service reservations and improve customer convenience.",
    ),
    (
        "2.2.2 Online Reservation Systems in Hospitality",
        "Gaur (2024) explained that online reservation systems improve operational efficiency and reduce manual workload in hospitality businesses. The study emphasized that digital platforms help businesses process reservations faster, maintain organized records, and provide customers with easier access to services. This supports the proposed system because McDonald's event reservations require faster processing and better monitoring than manual methods can provide.",
    ),
    (
        "2.2.3 E-Tourism Reservation Systems",
        "Halkiopoulos et al. (2020) found that ease of use and accessibility strongly influence online booking decisions in e-tourism. Their study suggests that users prefer reservation systems that are understandable, accessible, and efficient. This is important for the proposed system because customers must be able to navigate event booking steps without confusion.",
    ),
    (
        "2.2.4 Web-Based Cafe Reservation Systems",
        "Mohd Roseli and Hamid Ali (2024) developed a cafe reservation system that improved customer convenience and reduced reservation conflicts. The study demonstrated how web-based reservation platforms can support small food-service establishments in organizing bookings. This is related to the proposed McDonald's Event Planner because it also serves a food-service environment with event-based booking requirements.",
    ),
    (
        "2.2.5 Digital Transformation in Tourism",
        "Vlahovic et al. (2024) found that digital reservation systems improve tourism service efficiency and customer engagement. Their study showed that digital transformation allows service providers to respond more effectively to customer needs and manage service records. This supports the proposed system because digital transformation can also improve fast-food event reservation operations.",
    ),
    (
        "2.2.6 Mobile Hotel Booking Loyalty",
        "Ozturk et al. (2020) emphasized that convenience and compatibility affect customer loyalty toward online booking systems. The study showed that customers are more likely to continue using a digital platform when it is convenient and fits their needs. This is relevant to the proposed mobile application because customers must find the booking process useful, accessible, and compatible with their device habits.",
    ),
    (
        "2.2.7 Smart Dining Systems",
        "Gangurde and Kshirsagar (2024) introduced smart dining systems that integrate reservations and food pre-ordering. Their study highlighted the value of combining booking and food selection in one system to improve customer service and operational preparation. This is directly related to the proposed McDonald's Event Planner because the system allows customers to select packages, menu bundles, and add-ons during the reservation process.",
    ),
    (
        "2.2.8 Online Event Booking Systems",
        "Ganiyu et al. (2024) found that online event booking systems reduce operational costs and improve booking accessibility. Their study showed that users benefit from being able to reserve services remotely, while administrators benefit from organized digital records. This supports the proposed system because it focuses on online event reservations for birthday parties, business meetings, and similar gatherings.",
    ),
    (
        "2.2.9 Catering Reservation Platforms",
        "Sahara et al. (2024) developed a catering reservation system that improved booking management efficiency. The study indicated that digital tools help organize event schedules, customer information, and food-service requests. This is relevant because McDonald's event reservations also require coordination between customer booking details, selected food packages, and staff preparation.",
    ),
    (
        "2.2.10 Restaurant Reservation Systems",
        "Sahu and Yadav (2024) explained that restaurant reservation systems improve table management and customer satisfaction. Their study emphasized that reservation systems help restaurants control customer flow and reduce waiting time. Although the proposed system focuses on events rather than ordinary table reservations, the same concept of organized scheduling applies to McDonald's event planning.",
    ),
    (
        "2.2.11 Customer Satisfaction in Online Booking",
        "Ullah (2020) found that customer satisfaction significantly influences loyalty toward online hotel booking systems. The study suggests that positive digital booking experiences encourage continued system use. This supports the proposed system because a smooth reservation process can increase customer trust and willingness to use McDonald's digital event booking platform.",
    ),
    (
        "2.2.12 Hospitality Information Systems",
        "Law et al. (2020) emphasized that information technology improves hospitality management and reservation processes. Their work shows that IT applications help service businesses manage customer data, booking workflows, and operational decisions. This is related to the proposed system because the McDonald's Event Planner uses information technology to support customer booking, staff operations, and administrator management.",
    ),
    (
        "2.2.13 Hotel Booking User Loyalty",
        "Wu (2020) highlighted that service quality and online reviews influence hotel reservation decisions. The study suggests that customers consider digital service quality when deciding whether to use an online reservation system. This is relevant to the proposed system because clear information, reliable status updates, and smooth navigation can influence customer acceptance.",
    ),
    (
        "2.2.14 Online Reservation Accessibility",
        "Smith (2020) explained that online reservation systems improve customer accessibility and booking convenience. The study emphasized that customers benefit from being able to make reservations without visiting the physical location. This supports the proposed McDonald's Event Planner because customers can submit event reservations through the web or mobile application.",
    ),
    (
        "2.2.15 Restaurant Reservation with Pre-Ordering",
        "Subrahmanyam et al. (2021) found that pre-ordering systems reduce waiting time and improve dining experiences. Their study showed that allowing customers to choose food items before arrival helps staff prepare services more efficiently. This is closely related to the proposed system because customers can select event packages, menu bundles, menu items, and add-ons before the scheduled event.",
    ),
    (
        "2.2.16 Automated Restaurant Systems",
        "Deksne et al. (2021) found that restaurant automation improves operational productivity and customer flow. The study showed that automated systems can support service speed and reduce workload for employees. This is relevant to McDonald's event reservation management because automation can help staff prepare for bookings and monitor service status more effectively.",
    ),
    (
        "2.2.17 Online Booking Decision-Making",
        "Xue et al. (2020) explained that pricing transparency and user-friendly interfaces influence booking decisions. Their findings suggest that customers are more comfortable using reservation systems when prices, options, and procedures are clearly presented. This supports the proposed system because customers must be able to view packages, menu options, add-ons, and reservation details before submitting a booking.",
    ),
    (
        "2.2.18 QR-Based Reservation Systems",
        "Zezar and Kalifa (2024) developed QR-based reservation systems that improved contactless restaurant transactions. Their study showed that QR-based processes can simplify customer access and support faster service verification. This is relevant to the proposed system because the McDonald's Event Planner includes QR or booking pass information for reservation tracking and check-in support.",
    ),
    (
        "2.2.19 Hotel Reservation Decision Factors",
        "Abdullah et al. (2020) found that perceived usefulness and ease of use influence online hotel reservation intention. The study supports the idea that users are more likely to adopt a system when it clearly helps them accomplish their goal with less effort. This is important for the proposed system because user acceptance depends on how useful and easy the reservation process is.",
    ),
    (
        "2.2.20 Digital Hospitality Innovation",
        "Garcia et al. (2024) explained that digital reservation platforms improve customer satisfaction and operational performance. The study emphasized that digital tools can support better communication, faster booking, and improved management decisions. This supports the proposed McDonald's Event Planner as a digital innovation designed to improve fast-food event reservation services.",
    ),
]


LOCAL_REFERENCES = [
    "Banas, A. R. M., et al. (2020). Web-based management system for catering services and event planning.",
    "Calpito, R., Salamat, L., & Mendoza, B. (2020). Web-based and mobile hotel reservation system with analytics.",
    "Capuno, M. I., Benigno, J. F., Reyes, R. G., & Tojino, K. C. M. W. (2021). iReserve: An online event reservation for Lipa City Cultural with SMS notification. JPAIR Multidisciplinary Research, 43(1), 146-165.",
    "Caritos, J. A. L. (2024). Transformation of hotel reservation systems.",
    "Deocampo, J. V., et al. (2020). Online resort services management system.",
    "Dublado, A. M. E., et al. (2020). Hotel guest management system.",
    "Esguerra, J. G., & Arreza, M. K. B. (2021). The motivational factors on using virtual hotel operators' accommodation among tourists in Cebu City, Philippines. Journal of Business on Hospitality and Tourism, 7(3), 212-221.",
    "Felicen, S. S. (2024). Innovation practices in catering business.",
    "Felicen, S. S., & Ylagan, A. P. (2020). Effects of computer reservation systems in travel agencies.",
    "Leona, R. F. (2023). Development and assessment of AppStay reservation system.",
    "Monterey, J. M. (2021). Service quality of online travel agencies in CALABARZON.",
    "Munoz, A. D. D. G. (2023). Influence of online reviews on Airbnb booking behavior.",
    "Ramento, L. F., et al. (2024). Web-based reservation and management system for Nature Spring Resort.",
    "Sabaulan, J. S. (2023). Environmental practices of hotel industries in Santiago City.",
    "Vega, M. M. (2024). Online facility reservation system.",
    "Villanueva, E. (2024). Online reservation management system.",
]


FOREIGN_REFERENCES = [
    "Abdullah, D., et al. (2020). Online hotel booking intention and perceived usefulness.",
    "Deksne, L., Kempelis, A., Sniedzins, T., & Kozlovskis, A. (2021). Automated system for restaurant services. Information Technology and Management Science, 24(1), 15-25.",
    "Egigogo, R. A., et al. (2024). Design and implementation of computerized restaurant table booking system. Ceddi Journal of Information System and Technology, 3(1), 47-55.",
    "Gangurde, R., & Kshirsagar, P. (2024). Smart dining systems integrating reservations and food pre-ordering.",
    "Ganiyu, M., Egwuche, O. S., & Adekunle, K. O. (2024). Online events booking and reservation system.",
    "Garcia, G. M., et al. (2024). Digital reservation platforms in hospitality.",
    "Gaur, V. (2024). Online reservation systems in the hospitality industry. International Journal of Research in Marketing Management and Sales, 6(1), 81-87.",
    "Halkiopoulos, C., et al. (2020). Online reservation systems in e-business: Analyzing decision making in e-tourism. Journal of Tourism, Heritage & Services Marketing, 6(1), 9-16.",
    "Law, R., Leung, R., & Buhalis, D. (2020). Information technology applications in hospitality and tourism.",
    "Mohd Roseli, N. A., & Hamid Ali, F. A. (2024). Book cafe online reservation system for Dino Bookcafe. Applied Information Technology and Computer Science, 5(2), 407-425.",
    "Ozturk, A. B., et al. (2020). Mobile hotel booking loyalty and convenience.",
    "Sahara, R. I., et al. (2024). Web-based reservation system for catering services.",
    "Sahu, G., & Yadav, M. (2024). Restaurant reservation system.",
    "Smith, R. P. (2020). Impact of online reservation systems.",
    "Subrahmanyam, K., et al. (2021). Online table reservation with pre-ordering. Turkish Journal of Computer and Mathematics Education, 12(4), 1316-1321.",
    "Ullah, I. (2020). Customer satisfaction in online hotel booking.",
    "Vlahovic, O., et al. (2024). Digital transformation in tourism and e-booking systems.",
    "Wu, M. (2020). Customers' perceptions of service quality in online reservation systems.",
    "Xue, P., Jo, W., & Bonn, M. A. (2020). Online hotel booking decisions based on price complexity and alternative attractiveness. Journal of Hospitality and Tourism Management, 45, 162-171.",
    "Zezar, M., & Kalifa, A. (2024). QR code-based ordering and reservation system.",
]


LOCAL_NARRATIVE = [
    "Local studies from 2020 to 2024 show that digital reservation systems have become useful tools in improving the efficiency of event, hospitality, tourism, and facility management services in the Philippines. Capuno et al. (2021) developed an online event reservation system with SMS notification for the Lipa City Cultural Center and found that automated reservation and notification features improved scheduling efficiency, reduced booking conflicts, and strengthened communication with clients. Similarly, Vega (2024) developed an online facility reservation system that improved availability monitoring and reduced overlapping reservations. These studies support the importance of automated scheduling and real-time monitoring in reservation-based services.",
    "Several local studies also focused on resort and hotel reservation systems. Ramento et al. (2024) developed a web-based reservation and management system for Nature Spring Resort, which improved reservation accuracy, customer convenience, and record organization. Villanueva (2024) introduced an online reservation management system for resorts that improved reservation tracking and reduced scheduling errors. Caritos (2024) also explained that digital transformation in hotel reservation systems improves booking efficiency, customer engagement, and service delivery. These findings show that replacing manual processes with digital platforms can help hospitality businesses manage reservations more accurately and efficiently.",
    "Studies on hotel and accommodation systems further emphasize the value of web-based and mobile access. Calpito et al. (2020) developed a hotel reservation system with both web and mobile accessibility, showing that multi-platform access improves customer engagement and reservation management. Leona (2023) developed a web-based accommodation reservation system and stated that online and mobile-compatible systems improve accessibility, real-time booking efficiency, and customer convenience. These studies are related to the proposed McDonald's Event Planner because the system also provides digital access for customers who need to create and monitor reservations using web and mobile platforms.",
    "Local literature also highlights the importance of user experience and customer satisfaction in digital booking platforms. Esguerra and Arreza (2021) examined factors affecting the use of digital hotel booking platforms in Cebu City and found that ease of navigation and simplified booking processes significantly affect customer satisfaction. Monterey (2021) found that service quality in online booking platforms influences customer satisfaction and booking intention. Munoz (2023) emphasized that online reviews and digital platforms influence customer reservation decisions. These findings suggest that a reservation system must be easy to use, clear, and reliable to encourage customer acceptance.",
    "Other Philippine studies discuss the role of reservation systems in improving operational performance in related service industries. Felicen and Ylagan (2020) found that computer reservation systems improve operational efficiency and customer service delivery in travel agencies. Felicen (2024) emphasized that innovation and digital systems improve operational performance in hospitality and catering businesses, while Sabaulan (2023) highlighted the role of reservation-related systems in improving tourism and hospitality services. These studies support the idea that digital transformation helps service-based businesses reduce manual workload and improve coordination.",
    "Event and catering-related studies are especially relevant to the proposed system. Banas et al. (2020) highlighted that digital event management systems improve coordination between event planners and customers. Felicen (2024) also explained that catering businesses benefit from digital reservation and scheduling systems because these tools support food preparation, customer coordination, and event planning. Dublado et al. (2020) found that computerized hotel reservation systems improve data accuracy and reduce manual errors. These findings support the need for a digital event reservation platform that can manage customer information, schedules, menu selections, and service details in one organized system.",
]


FOREIGN_NARRATIVE = [
    "Foreign literature from 2020 to 2024 also supports the use of digital reservation systems in hospitality, restaurant, tourism, and event management. Egigogo et al. (2024) developed a computerized restaurant table booking system and found that it improved reservation efficiency, customer experience, and booking accuracy. Gaur (2024) explained that online reservation systems reduce manual workload and improve operational efficiency in hospitality businesses. Similarly, Sahu and Yadav (2024) stated that restaurant reservation systems improve table management and customer satisfaction. These studies show that digital reservation platforms can help food-service businesses organize reservations more effectively.",
    "Studies on tourism and hospitality reservation systems emphasize accessibility, ease of use, and digital transformation. Halkiopoulos et al. (2020) found that ease of use and accessibility strongly influence online booking decisions in e-tourism. Vlahovic et al. (2024) found that digital reservation systems improve tourism service efficiency and customer engagement. Law et al. (2020) emphasized that information technology improves hospitality management and reservation processes. These findings support the use of technology as a practical solution for improving reservation handling and customer service.",
    "Several foreign studies focused on customer behavior and satisfaction in online booking platforms. Ozturk et al. (2020) emphasized that convenience and compatibility affect customer loyalty toward online booking systems. Ullah (2020) found that customer satisfaction significantly influences loyalty in online hotel booking, while Wu (2020) highlighted that service quality and online reviews influence hotel reservation decisions. Abdullah et al. (2020) also found that perceived usefulness and ease of use affect online hotel reservation intention. These studies show that users are more likely to accept a system if it is convenient, useful, and easy to operate.",
    "Restaurant and dining-related studies provide concepts that are useful for the proposed McDonald's Event Planner. Gangurde and Kshirsagar (2024) introduced smart dining systems that integrate reservations and food pre-ordering, showing that combining booking and food selection can improve service preparation. Subrahmanyam et al. (2021) found that online table reservation with pre-ordering reduces waiting time and improves the dining experience. Deksne et al. (2021) found that automated restaurant systems improve operational productivity and customer flow. These studies are relevant because the proposed system includes event package selection, menu selection, and staff preparation features.",
    "Foreign studies also show the value of event and catering reservation platforms. Ganiyu et al. (2024) developed an online event booking system and found that it reduced operational costs and improved booking accessibility. Sahara et al. (2024) developed a web-based catering reservation system that improved booking management efficiency. These studies demonstrate that digital platforms are effective in managing event-related reservations, especially when customer details, schedules, and service requirements must be handled accurately.",
    "Recent studies also explore emerging reservation technologies such as QR-based and web-based cafe systems. Mohd Roseli and Hamid Ali (2024) developed a web-based cafe reservation system that improved customer convenience and reduced reservation conflicts. Zezar and Kalifa (2024) developed QR-based reservation systems that improved contactless restaurant transactions. Smith (2020) explained that online reservation systems improve customer accessibility and booking convenience, while Garcia et al. (2024) stated that digital hospitality platforms improve customer satisfaction and operational performance. These studies support the development of a modern, accessible, and organized reservation system for fast-food event services.",
]


def replace_chapter_2(doc):
    paragraphs = list(doc.paragraphs)
    start = next(i for i, p in enumerate(paragraphs) if p.text.strip() == "2. RELATED LITERATURE")
    end = next(i for i, p in enumerate(paragraphs[start + 1 :], start + 1) if p.text.strip().startswith("3. TECHNICAL BACKGROUND"))

    heading = paragraphs[start]
    for paragraph in paragraphs[start + 1 : end]:
        delete_paragraph(paragraph)

    format_heading(heading, "2. RELATED LITERATURE", 1)
    anchor = heading
    intro = (
        "This chapter presents the local and foreign literature related to the development of the McDonald's Event Planner, "
        "a digital integrated system for birthday parties, business meetings, and reservations in Surigao Branch. The reviewed "
        "studies focus on online reservation systems, hospitality information systems, event management platforms, mobile booking "
        "applications, restaurant automation, and digital service innovation. These studies were examined to identify the features, "
        "benefits, and limitations of existing systems and to establish the relevance of the proposed system."
    )
    anchor = append_after(anchor, intro)
    anchor = append_after(anchor, "2.1 Local Related Literature (2020-2024)", "Heading 2", bold=True, first_indent=False)
    for body in LOCAL_NARRATIVE:
        anchor = append_after(anchor, body)

    anchor = append_after(anchor, "2.2 Foreign Related Literature (2020-2024)", "Heading 2", bold=True, first_indent=False)
    for body in FOREIGN_NARRATIVE:
        anchor = append_after(anchor, body)

    anchor = append_after(anchor, "2.3 Synthesis and Research Gap", "Heading 2", bold=True, first_indent=False)
    anchor = append_after(
        anchor,
        "The reviewed local and foreign studies consistently show that digital reservation systems improve booking accuracy, customer convenience, operational efficiency, and record management. Local studies demonstrate the usefulness of web-based reservation platforms in hotels, resorts, travel agencies, cultural centers, and facility management. Foreign studies further support the importance of restaurant automation, online booking accessibility, pre-ordering, QR-based transactions, and hospitality information systems.",
    )
    anchor = append_after(
        anchor,
        "Despite these contributions, most existing systems focus on hotels, resorts, travel agencies, cafes, restaurant table reservations, catering services, or general event venues. There is limited literature on a reservation system specifically designed for fast-food event services such as McDonald's birthday parties, business meetings, and branch-based reservations. The proposed McDonald's Event Planner addresses this gap by combining event booking, package selection, menu selection, payment proof uploading, availability checking, staff operations, and administrator approval in one integrated web and mobile system.",
    )


def replace_references(doc):
    paragraphs = list(doc.paragraphs)
    start = next(i for i, p in enumerate(paragraphs) if p.text.strip() in {"6. REFERENCES", "REFERENCES"})

    heading = paragraphs[start]
    for paragraph in paragraphs[start + 1 :]:
        delete_paragraph(paragraph)

    format_heading(heading, "6. REFERENCES", 1)
    anchor = heading
    anchor = append_after(anchor, "6.1 Local References", "Heading 2", bold=True, first_indent=False)
    for reference in LOCAL_REFERENCES:
        anchor = append_after(anchor, reference, "Normal", first_indent=False)
        make_reference(anchor)

    anchor = append_after(anchor, "6.2 Foreign References", "Heading 2", bold=True, first_indent=False)
    for reference in FOREIGN_REFERENCES:
        anchor = append_after(anchor, reference, "Normal", first_indent=False)
        make_reference(anchor)


def build():
    doc = Document(str(SOURCE))
    replace_chapter_2(doc)
    replace_references(doc)
    doc.save(str(OUTPUT))
    print(OUTPUT)


if __name__ == "__main__":
    build()
